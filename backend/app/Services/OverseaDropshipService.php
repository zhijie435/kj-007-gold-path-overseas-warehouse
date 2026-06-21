<?php

namespace App\Services;

use App\Enums\DropshipOrderStatus;
use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use App\Models\OverseaWarehouseConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OverseaDropshipService
{
    public function createDropshipOrder(array $data, User $user): DropshipOrder
    {
        return DB::transaction(function () use ($data, $user): DropshipOrder {
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new InvalidArgumentException('代发单至少需要一个商品明细');
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
        $currentStatus = $order->getStatusEnum();
        $allowed = [DropshipOrderStatus::DRAFT, DropshipOrderStatus::PENDING_REVIEW];
        if (!in_array($currentStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('当前状态 [%s] 不允许审核操作', $currentStatus->label())
            );
        }

        $targetStatus = $pass
            ? ($reviewer === null ? DropshipOrderStatus::AUTO_REVIEW_PASS : DropshipOrderStatus::REVIEW_PASS)
            : DropshipOrderStatus::REVIEW_REJECT;

        $order->status = $targetStatus;
        $order->reviewed_at = now();
        $order->review_remark = $remark;
        if ($reviewer !== null) {
            $order->reviewed_by = $reviewer->id;
        }

        $order->save();

        return $order;
    }

    public function pushToWms(DropshipOrder $order): DropshipOrder
    {
        $currentStatus = $order->getStatusEnum();
        $pushable = [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
        ];
        if (!in_array($currentStatus, $pushable, true)) {
            throw new InvalidArgumentException(
                sprintf('当前状态 [%s] 不允许推送到WMS', $currentStatus->label())
            );
        }

        if (empty($order->warehouse_id)) {
            throw new RuntimeException('未分配海外仓，无法推送');
        }

        $warehouseConfig = OverseaWarehouseConfig::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->active()
            ->first();

        if ($warehouseConfig === null) {
            throw new RuntimeException('海外仓配置不存在或未启用');
        }

        $this->updateDropshipStatus($order, DropshipOrderStatus::PUSHING);
        $order->push_attempts = ($order->push_attempts ?? 0) + 1;
        $order->save();

        $wmsService = app(WmsIntegrationService::class);

        try {
            $result = $wmsService->sendOrder($warehouseConfig, $order);
            $this->updateDropshipStatus($order, DropshipOrderStatus::PUSH_SUCCESS, [
                'wms_response' => $result,
            ]);
            if (!empty($result['wms_order_no'])) {
                $order->wms_order_no = $result['wms_order_no'];
                $order->save();
            }
        } catch (\Throwable $e) {
            $order->push_error = $e->getMessage();
            $order->save();
            $this->updateDropshipStatus($order, DropshipOrderStatus::PUSH_FAILED, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $order;
    }

    public function updateDropshipStatus(
        DropshipOrder $order,
        DropshipOrderStatus $status,
        array $context = [],
    ): DropshipOrder {
        $currentStatus = $order->getStatusEnum();

        if (!$currentStatus->canTransitionTo($status)) {
            throw new InvalidArgumentException(sprintf(
                '状态流转不合法：%s -> %s',
                $currentStatus->label(),
                $status->label()
            ));
        }

        $order->status = $status;

        $timestampField = $status->timestampField();
        if ($timestampField !== null && empty($order->{$timestampField})) {
            $order->{$timestampField} = now();
        }

        if (!empty($context)) {
            $extraData = $order->extra_data ?? [];
            $extraData['status_context'] = [
                'from' => $currentStatus->value,
                'to' => $status->value,
                'time' => now()->toDateTimeString(),
                'context' => $context,
            ];
            $order->extra_data = $extraData;
        }

        $order->save();

        return $order;
    }

    public function cancelOrder(DropshipOrder $order, string $reason): DropshipOrder
    {
        $currentStatus = $order->getStatusEnum();
        if ($currentStatus->isTerminal()) {
            throw new InvalidArgumentException(
                sprintf('当前状态 [%s] 为终态，无法取消', $currentStatus->label())
            );
        }

        DB::transaction(function () use ($order, $reason): void {
            $extraData = $order->extra_data ?? [];
            $extraData['cancel_reason'] = $reason;
            $extraData['cancelled_by'] = auth()->id();
            $order->extra_data = $extraData;

            $this->updateDropshipStatus($order, DropshipOrderStatus::CANCELLED, [
                'reason' => $reason,
            ]);
        });

        return $order;
    }

    public function getStatistics(): array
    {
        $counts = DropshipOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $stats = [];
        foreach (DropshipOrderStatus::cases() as $case) {
            $stats[$case->value] = [
                'label' => $case->label(),
                'color' => $case->color(),
                'count' => (int) ($counts[$case->value] ?? 0),
            ];
        }

        $stats['summary'] = [
            'total' => array_sum(array_column($stats, 'count')),
            'pending_review' => $stats[DropshipOrderStatus::PENDING_REVIEW->value]['count'],
            'pending_push' => $stats[DropshipOrderStatus::REVIEW_PASS->value]['count']
                + $stats[DropshipOrderStatus::AUTO_REVIEW_PASS->value]['count']
                + $stats[DropshipOrderStatus::PUSH_FAILED->value]['count'],
            'in_transit' => $stats[DropshipOrderStatus::SHIPPED->value]['count']
                + $stats[DropshipOrderStatus::IN_TRANSIT->value]['count']
                + $stats[DropshipOrderStatus::CUSTOMS->value]['count'],
            'completed' => $stats[DropshipOrderStatus::COMPLETED->value]['count'],
            'exception' => $stats[DropshipOrderStatus::EXCEPTION->value]['count'],
        ];

        return $stats;
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
