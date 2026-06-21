<?php

namespace App\Services;

use App\Enums\DropshipOrderStatus;
use App\Enums\WmsCallbackType;
use App\Models\DropshipOrder;
use App\Models\OverseaWarehouseConfig;
use App\Models\WmsCallbackLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WmsIntegrationService
{
    public function sendOrder(OverseaWarehouseConfig $config, DropshipOrder $order): array
    {
        $this->updateLastApiCall($config);

        $payload = $this->buildOrderPayload($config, $order);
        $response = $this->callApi($config, 'order/create', $payload);

        if (empty($response['success'])) {
            throw new RuntimeException(sprintf(
                'WMS[%s]推单失败：%s',
                $config->wms_provider,
                $response['message'] ?? '未知错误'
            ));
        }

        return [
            'wms_order_no' => $response['data']['order_no'] ?? null,
            'raw_response' => $response,
        ];
    }

    public function fetchInventory(OverseaWarehouseConfig $config): array
    {
        $this->updateLastApiCall($config);

        $response = $this->callApi($config, 'inventory/list', [
            'warehouse_code' => $config->warehouse_code,
        ]);

        if (empty($response['success'])) {
            throw new RuntimeException(sprintf(
                'WMS[%s]拉取库存失败：%s',
                $config->wms_provider,
                $response['message'] ?? '未知错误'
            ));
        }

        $config->last_inventory_sync_at = now();
        $config->save();

        return $response['data']['items'] ?? [];
    }

    public function fetchTracking(OverseaWarehouseConfig $config, DropshipOrder $order): array
    {
        $this->updateLastApiCall($config);

        $payload = [
            'warehouse_code' => $config->warehouse_code,
            'order_no' => $order->wms_order_no ?? $order->dropship_no,
        ];

        if (!empty($order->tracking_no)) {
            $payload['tracking_no'] = $order->tracking_no;
        }

        $response = $this->callApi($config, 'tracking/query', $payload);

        if (empty($response['success'])) {
            throw new RuntimeException(sprintf(
                'WMS[%s]拉取物流轨迹失败：%s',
                $config->wms_provider,
                $response['message'] ?? '未知错误'
            ));
        }

        $trackingData = $response['data'] ?? [];

        if (!empty($trackingData['tracking_no']) && empty($order->tracking_no)) {
            $order->tracking_no = $trackingData['tracking_no'];
        }
        if (!empty($trackingData['carrier_name'])) {
            $order->carrier_name = $trackingData['carrier_name'];
        }
        if (!empty($trackingData['events']) && is_array($trackingData['events'])) {
            foreach ($trackingData['events'] as $event) {
                $order->addTrackingEvent(
                    $event['status'] ?? '',
                    $event['location'] ?? '',
                    $event['description'] ?? ''
                );
            }
        }

        $this->updateOrderStatusByTracking($order, $trackingData);
        $order->save();

        $config->last_tracking_sync_at = now();
        $config->save();

        return $trackingData;
    }

    public function handleCallback(WmsCallbackLog $log): void
    {
        DB::transaction(function () use ($log): void {
            $log->markProcessing();

            try {
                $callbackType = $log->getTypeEnum();
                $body = $log->getRequestBodyArray();

                match ($callbackType) {
                    WmsCallbackType::ORDER_STATUS => $this->handleOrderStatusCallback($log, $body),
                    WmsCallbackType::SHIPMENT => $this->handleShipmentCallback($log, $body),
                    WmsCallbackType::TRACKING => $this->handleTrackingCallback($log, $body),
                    WmsCallbackType::INVENTORY => $this->handleInventoryCallback($log, $body),
                    WmsCallbackType::STOCK_ADJUST => $this->handleStockAdjustCallback($log, $body),
                };

                $log->markSuccess(json_encode(['processed' => true], JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $log->markFailed(
                    (string) $e->getCode(),
                    $e->getMessage()
                );
                throw $e;
            }
        });
    }

    public function testConnection(OverseaWarehouseConfig $config): array
    {
        try {
            $this->updateLastApiCall($config);
            $response = $this->callApi($config, 'system/ping', ['timestamp' => time()]);

            $success = !empty($response['success']) || !empty($response['data']['pong']);
            return [
                'success' => $success,
                'latency' => $response['latency'] ?? 0,
                'message' => $success ? '连接成功' : ($response['message'] ?? '连接失败'),
                'raw' => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    protected function buildOrderPayload(OverseaWarehouseConfig $config, DropshipOrder $order): array
    {
        $items = $order->items->map(function ($item): array {
            return [
                'sku' => $item->sku,
                'product_name' => $item->product_name,
                'specification' => $item->specification,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'weight' => (string) $item->weight,
                'hs_code' => $item->hs_code,
                'batch_no' => $item->batch_no,
            ];
        })->toArray();

        return [
            'warehouse_code' => $config->warehouse_code,
            'out_order_no' => $order->dropship_no,
            'order_id' => $order->order_id,
            'shipping_method' => $order->shipping_method_code ?? $config->default_shipping_method,
            'currency' => $order->currency ?? 'USD',
            'declared_value' => (string) $order->declared_value,
            'receiver' => [
                'name' => $order->receiver_name,
                'phone' => $order->receiver_phone,
                'email' => $order->receiver_email,
                'country' => $order->receiver_country,
                'state' => $order->receiver_state,
                'city' => $order->receiver_city,
                'postal_code' => $order->receiver_postal_code,
                'address' => $order->receiver_address,
            ],
            'items' => $items,
            'extra' => [
                'total_weight' => (string) $order->weight,
                'volume_weight' => (string) $order->volume_weight,
                'customs_info' => $order->customs_info,
                'remark' => $order->remark,
            ],
        ];
    }

    protected function callApi(OverseaWarehouseConfig $config, string $endpoint, array $payload): array
    {
        $startTime = microtime(true);

        $headers = $this->buildHeaders($config);
        $url = rtrim($config->api_endpoint, '/') . '/' . ltrim($endpoint, '/');
        $signedPayload = $this->signPayload($config, $payload);

        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->asJson()
                ->post($url, $signedPayload);

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $body = $response->json() ?? [];
            $body['latency'] = $latency;

            if (!$response->successful()) {
                $body['success'] = false;
                $body['message'] = $body['message'] ?? sprintf('HTTP %s: %s', $response->status(), $response->body());
            }

            return $body;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    protected function buildHeaders(OverseaWarehouseConfig $config): array
    {
        return [
            'X-WMS-Provider' => $config->wms_provider,
            'X-WMS-Warehouse' => $config->warehouse_code,
            'X-Request-Id' => 'req_' . bin2hex(random_bytes(8)),
            'Content-Type' => 'application/json',
        ];
    }

    protected function signPayload(OverseaWarehouseConfig $config, array $payload): array
    {
        $payload['app_key'] = $config->api_key;
        $payload['timestamp'] = time();
        $payload['nonce'] = bin2hex(random_bytes(4));

        ksort($payload);
        $signString = http_build_query($payload) . '&secret=' . $config->api_secret;
        $payload['sign'] = strtoupper(md5($signString));

        return $payload;
    }

    protected function updateLastApiCall(OverseaWarehouseConfig $config): void
    {
        $config->last_api_call_at = now();
        $config->save();
    }

    protected function updateOrderStatusByTracking(DropshipOrder $order, array $trackingData): void
    {
        $status = $trackingData['status'] ?? null;
        if (empty($status)) {
            return;
        }

        $statusMap = [
            'processing' => DropshipOrderStatus::PROCESSING,
            'picked' => DropshipOrderStatus::PICKED,
            'packed' => DropshipOrderStatus::PACKED,
            'shipped' => DropshipOrderStatus::SHIPPED,
            'in_transit' => DropshipOrderStatus::IN_TRANSIT,
            'transit' => DropshipOrderStatus::IN_TRANSIT,
            'customs' => DropshipOrderStatus::CUSTOMS,
            'clearance' => DropshipOrderStatus::CUSTOMS,
            'delivered' => DropshipOrderStatus::DELIVERED,
            'completed' => DropshipOrderStatus::COMPLETED,
            'returned' => DropshipOrderStatus::RETURNED,
            'exception' => DropshipOrderStatus::EXCEPTION,
        ];

        $targetStatus = $statusMap[strtolower($status)] ?? null;
        if ($targetStatus !== null) {
            $dropshipService = app(OverseaDropshipService::class);
            try {
                $dropshipService->updateDropshipStatus($order, $targetStatus, ['source' => 'tracking_sync']);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    protected function handleOrderStatusCallback(WmsCallbackLog $log, array $body): void
    {
        $order = $this->findOrderByCallback($log, $body);
        if ($order === null) {
            return;
        }

        $wmsStatus = $body['data']['status'] ?? $body['status'] ?? null;
        if ($wmsStatus === null) {
            return;
        }

        $this->updateOrderStatusByTracking($order, ['status' => $wmsStatus]);
    }

    protected function handleShipmentCallback(WmsCallbackLog $log, array $body): void
    {
        $order = $this->findOrderByCallback($log, $body);
        if ($order === null) {
            return;
        }

        $data = $body['data'] ?? $body;

        if (!empty($data['tracking_no'])) {
            $order->tracking_no = $data['tracking_no'];
        }
        if (!empty($data['carrier_name'])) {
            $order->carrier_name = $data['carrier_name'];
        }
        if (!empty($data['packages']) && is_array($data['packages'])) {
            $extra = $order->extra_data ?? [];
            $extra['packages'] = $data['packages'];
            $order->extra_data = $extra;
        }

        $dropshipService = app(OverseaDropshipService::class);
        try {
            $dropshipService->updateDropshipStatus($order, DropshipOrderStatus::SHIPPED, [
                'source' => 'shipment_callback',
                'tracking_no' => $order->tracking_no,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $order->save();
    }

    protected function handleTrackingCallback(WmsCallbackLog $log, array $body): void
    {
        $order = $this->findOrderByCallback($log, $body);
        if ($order === null) {
            return;
        }

        $events = $body['data']['events'] ?? $body['events'] ?? [];
        if (is_array($events)) {
            foreach ($events as $event) {
                $order->addTrackingEvent(
                    $event['status'] ?? '',
                    $event['location'] ?? '',
                    $event['description'] ?? ''
                );
            }
        }

        if (!empty($body['data']['tracking_no'])) {
            $order->tracking_no = $body['data']['tracking_no'];
        }

        $this->updateOrderStatusByTracking($order, $body['data'] ?? $body);
        $order->save();
    }

    protected function handleInventoryCallback(WmsCallbackLog $log, array $body): void
    {
    }

    protected function handleStockAdjustCallback(WmsCallbackLog $log, array $body): void
    {
    }

    protected function findOrderByCallback(WmsCallbackLog $log, array $body): ?DropshipOrder
    {
        if (!empty($log->dropship_order_id)) {
            $order = DropshipOrder::query()->find($log->dropship_order_id);
            if ($order !== null) {
                return $order;
            }
        }

        $wmsOrderNo = $body['data']['wms_order_no']
            ?? $body['data']['order_no']
            ?? $body['wms_order_no']
            ?? $body['order_no']
            ?? $log->wms_order_no
            ?? null;

        if ($wmsOrderNo !== null) {
            $order = DropshipOrder::query()->where('wms_order_no', $wmsOrderNo)->first();
            if ($order !== null) {
                if (empty($log->dropship_order_id)) {
                    $log->dropship_order_id = $order->id;
                    $log->save();
                }
                return $order;
            }
        }

        $outOrderNo = $body['data']['out_order_no']
            ?? $body['data']['reference_no']
            ?? $body['out_order_no']
            ?? $body['reference_no']
            ?? $log->reference_no
            ?? null;

        if ($outOrderNo !== null) {
            $order = DropshipOrder::query()->where('dropship_no', $outOrderNo)->first();
            if ($order !== null) {
                if (empty($log->dropship_order_id)) {
                    $log->dropship_order_id = $order->id;
                    $log->save();
                }
                return $order;
            }
        }

        return null;
    }
}
