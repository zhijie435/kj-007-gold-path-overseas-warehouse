<?php

namespace App\Services;

use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use App\Models\OverseaWarehouseConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OverseaDropshipService
{
    public function __construct(
        protected DropshipStateMachine $stateMachine,
    ) {}

    public function createDropshipOrder(array $data, User $user): DropshipOrder
    {
        return DB::transaction(function () use ($data, $user): DropshipOrder {
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw DropshipException::emptyItems();
            }

            $order = new DropshipOrder();
            $order->fill($data);
            $order->dropship_no = $order->generateDropshipNo();
            $order->created_by = $user->id;
            $order->status = DropshipOrderStatus::DRAFT;
            $order->total_items = array_sum(array_column($items, 'quantity'));

            if (empty($order->warehouse_id) && !empty($order->receiver_country)) {
                $warehouse = $this->assignWarehouseByCountry($order->receiver_country);
                if ($warehouse !== null) {
                    $order->warehouse_id = $warehouse->warehouse_id;
                }
            }

            $order->save();

            $itemsSubtotal = 0;
            foreach ($items as $itemData) {
                $item = new DropshipOrderItem();
                $item->fill($itemData);
                $item->dropship_order_id = $order->id;
                if ($item->subtotal === null) {
                    $item->subtotal = $item->calculateSubtotal();
                }
                $item->save();
                $itemsSubtotal += (float) $item->subtotal;
            }

            $order->subtotal = round($itemsSubtotal, 2);
            $order->total_cost = $order->calculateTotalCost();
            $order->save();

            return $order->load('items');
        });
    }

    public function reviewOrder(
        DropshipOrder $order,
        bool $pass,
        ?string $remark = null,
        ?User $reviewer = null,
    ): DropshipOrder {
        $this->stateMachine->ensureCanReview($order);

        $targetStatus = $pass
            ? ($reviewer === null ? DropshipOrderStatus::AUTO_REVIEW_PASS : DropshipOrderStatus::REVIEW_PASS)
            : DropshipOrderStatus::REVIEW_REJECT;

        return DB::transaction(function () use ($order, $targetStatus, $remark, $reviewer): DropshipOrder {
            $order = $this->stateMachine->transition($order, $targetStatus, [
                'action' => 'review',
                'pass' => $pass,
                'remark' => $remark,
                'reviewer_id' => $reviewer?->id,
            ]);

            $order->review_remark = $remark;
            if ($reviewer !== null) {
                $order->reviewed_by = $reviewer->id;
            }
            $order->save();

            return $order;
        });
    }

    public function pushToWms(DropshipOrder $order): DropshipOrder
    {
        $this->stateMachine->ensureCanPushToWms($order);

        if (empty($order->warehouse_id)) {
            throw DropshipException::warehouseNotAssigned();
        }

        $warehouseConfig = OverseaWarehouseConfig::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->active()
            ->first();

        if ($warehouseConfig === null) {
            throw DropshipException::warehouseConfigInvalid();
        }

        $order = $this->stateMachine->transition($order, DropshipOrderStatus::PUSHING, [
            'source' => 'manual',
        ]);
        $order->push_attempts = ($order->push_attempts ?? 0) + 1;
        $order->save();

        $wmsService = app(WmsIntegrationService::class);

        try {
            $result = $wmsService->sendOrder($warehouseConfig, $order);
            $this->stateMachine->transition($order->fresh(), DropshipOrderStatus::PUSH_SUCCESS, [
                'wms_response' => $result,
            ]);
            if (!empty($result['wms_order_no'])) {
                $order->wms_order_no = $result['wms_order_no'];
                $order->save();
            }
        } catch (\Throwable $e) {
            $order->push_error = $e->getMessage();
            $order->save();
            $this->stateMachine->transition($order->fresh(), DropshipOrderStatus::PUSH_FAILED, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $order->fresh();
    }

    public function updateDropshipStatus(
        DropshipOrder $order,
        DropshipOrderStatus $status,
        array $context = [],
    ): DropshipOrder {
        return $this->stateMachine->transition($order, $status, $context);
    }

    public function cancelOrder(DropshipOrder $order, string $reason, ?User $operator = null): DropshipOrder
    {
        $this->stateMachine->ensureCanCancel($order);

        return DB::transaction(function () use ($order, $reason, $operator): DropshipOrder {
            $extraData = $order->extra_data ?? [];
            $extraData['cancel_reason'] = $reason;
            $extraData['cancelled_by'] = $operator?->id ?? auth()->id();
            $order->extra_data = $extraData;
            $order->save();

            return $this->stateMachine->transition($order, DropshipOrderStatus::CANCELLED, [
                'reason' => $reason,
            ]);
        });
    }

    public function getStatistics(): array
    {
        return app(DropshipQueryService::class)->getStatusSummary();
    }

    public function getWarehouseOptions(): array
    {
        return OverseaWarehouseConfig::query()
            ->active()
            ->with('warehouse')
            ->get()
            ->map(function (OverseaWarehouseConfig $config): array {
                $warehouse = $config->warehouse;
                return [
                    'warehouse_id' => $config->warehouse_id,
                    'warehouse_code' => $config->warehouse_code,
                    'warehouse_name' => $warehouse?->name ?? $config->warehouse_code,
                    'wms_provider' => $config->wms_provider,
                    'supported_countries' => $config->getSupportedCountriesArray(),
                    'default_shipping_method' => $config->default_shipping_method,
                    'handling_fee' => $config->handling_fee,
                ];
            })
            ->toArray();
    }

    public function assignWarehouseByCountry(string $countryCode): ?OverseaWarehouseConfig
    {
        $countryUpper = strtoupper($countryCode);

        $warehouses = OverseaWarehouseConfig::query()
            ->active()
            ->get();

        $matched = null;
        foreach ($warehouses as $warehouse) {
            if ($warehouse->supportsCountry($countryUpper)) {
                $countries = $warehouse->getSupportedCountriesArray();
                if (!empty($countries)) {
                    $matched = $warehouse;
                    break;
                }
                if ($matched === null) {
                    $matched = $warehouse;
                }
            }
        }

        return $matched;
    }
}
