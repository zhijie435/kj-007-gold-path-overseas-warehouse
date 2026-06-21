<?php

namespace App\Http\Controllers;

use App\Enums\DropshipOrderStatus;
use App\Enums\WmsCallbackType;
use App\Http\Resources\WmsCallbackLogResource;
use App\Models\DropshipOrder;
use App\Models\WmsCallbackLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WmsCallbackLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WmsCallbackLog::query()
            ->with(['warehouse', 'dropshipOrder', 'processor'])
            ->when($request->filled('callback_type'), fn ($q) => $q->byType($request->string('callback_type')))
            ->when($request->filled('status'), fn ($q) => $q->byStatus($request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('wms_provider'), fn ($q) => $q->byProvider($request->string('wms_provider')))
            ->when($request->filled('dropship_order_id'), fn ($q) => $q->byOrder($request->integer('dropship_order_id')))
            ->when($request->filled('request_id'), fn ($q) => $q->byRequestId($request->string('request_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->string('keyword');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('wms_order_no', 'like', "%{$keyword}%")
                        ->orWhere('reference_no', 'like', "%{$keyword}%")
                        ->orWhere('request_id', 'like', "%{$keyword}%")
                        ->orWhereHas('dropshipOrder', function ($oq) use ($keyword) {
                            $oq->where('dropship_no', 'like', "%{$keyword}%");
                        });
                });
            })
            ->when($request->filled('date_range'), function ($q) use ($request) {
                [$start, $end] = $request->input('date_range');
                $q->whereBetween('created_at', [$start, $end]);
            })
            ->orderByDesc('id');

        $perPage = $request->integer('per_page', 20);
        $logs = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return WmsCallbackLogResource::collection($logs);
    }

    public function show(WmsCallbackLog $log): WmsCallbackLogResource
    {
        $log->load(['warehouse', 'dropshipOrder', 'processor']);

        return new WmsCallbackLogResource($log);
    }

    public function retry(WmsCallbackLog $log): JsonResponse
    {
        if (!$log->isFailed() && !$log->isRetry()) {
            return response()->json([
                'success' => false,
                'message' => '只有失败或重试状态的日志才能重新处理',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();

            $log->markProcessing();

            $type = $log->getTypeEnum();
            $requestBody = $log->getRequestBodyArray();

            $processed = false;
            $responseData = [];

            switch ($type) {
                case WmsCallbackType::ORDER_STATUS:
                    $processed = $this->processOrderStatusCallback($log, $requestBody, $responseData);
                    break;
                case WmsCallbackType::SHIPMENT:
                    $processed = $this->processShipmentCallback($log, $requestBody, $responseData);
                    break;
                case WmsCallbackType::TRACKING:
                    $processed = $this->processTrackingCallback($log, $requestBody, $responseData);
                    break;
                case WmsCallbackType::INVENTORY:
                    $processed = $this->processInventoryCallback($log, $requestBody, $responseData);
                    break;
                case WmsCallbackType::STOCK_ADJUST:
                    $processed = $this->processStockAdjustCallback($log, $requestBody, $responseData);
                    break;
                default:
                    $processed = true;
                    $responseData = ['message' => '未知类型，标记为成功'];
            }

            if ($processed) {
                $log->markSuccess(json_encode($responseData, JSON_UNESCAPED_UNICODE));
                $log->processed_by = request()->user()?->id;
                $log->save();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => new WmsCallbackLogResource($log->fresh()),
                    'message' => '回调处理成功',
                ]);
            }

            throw new \RuntimeException('回调处理逻辑返回失败');
        } catch (\Throwable $e) {
            DB::rollBack();

            $log->markFailed('RETRY_ERROR', $e->getMessage(), 5);

            return response()->json([
                'success' => false,
                'message' => '回调处理失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function processOrderStatusCallback(WmsCallbackLog $log, array $body, array &$responseData): bool
    {
        $order = $log->dropshipOrder;
        if (!$order) {
            $wmsOrderNo = $body['wms_order_no'] ?? $log->wms_order_no;
            $order = DropshipOrder::query()
                ->where('wms_order_no', $wmsOrderNo)
                ->first();
        }

        if (!$order) {
            throw new \RuntimeException('找不到关联的代发订单');
        }

        $statusMap = [
            'processing' => DropshipOrderStatus::PROCESSING,
            'picked' => DropshipOrderStatus::PICKED,
            'packed' => DropshipOrderStatus::PACKED,
            'shipped' => DropshipOrderStatus::SHIPPED,
            'in_transit' => DropshipOrderStatus::IN_TRANSIT,
            'customs' => DropshipOrderStatus::CUSTOMS,
            'delivered' => DropshipOrderStatus::DELIVERED,
            'completed' => DropshipOrderStatus::COMPLETED,
            'cancelled' => DropshipOrderStatus::CANCELLED,
            'returned' => DropshipOrderStatus::RETURNED,
            'exception' => DropshipOrderStatus::EXCEPTION,
        ];

        $wmsStatus = $body['status'] ?? '';
        if (isset($statusMap[$wmsStatus])) {
            $targetStatus = $statusMap[$wmsStatus];
            if ($order->getStatusEnum()->canTransitionTo($targetStatus)) {
                $order->status = $targetStatus;
                $timestampField = $targetStatus->timestampField();
                if ($timestampField !== null) {
                    $order->{$timestampField} = now();
                }
                $order->save();
                $responseData = [
                    'order_id' => $order->id,
                    'old_status' => $order->getOriginal('status'),
                    'new_status' => $targetStatus->value,
                ];
            } else {
                $responseData = [
                    'message' => '状态无需变更或不允许跳转',
                    'current_status' => $order->status,
                    'target_status' => $targetStatus->value,
                ];
            }
        } else {
            $responseData = ['message' => '未知WMS状态: ' . $wmsStatus];
        }

        return true;
    }

    private function processShipmentCallback(WmsCallbackLog $log, array $body, array &$responseData): bool
    {
        $order = $log->dropshipOrder;
        if (!$order) {
            $wmsOrderNo = $body['wms_order_no'] ?? $log->wms_order_no;
            $order = DropshipOrder::query()
                ->where('wms_order_no', $wmsOrderNo)
                ->first();
        }

        if (!$order) {
            throw new \RuntimeException('找不到关联的代发订单');
        }

        if (!empty($body['tracking_no'])) {
            $order->tracking_no = $body['tracking_no'];
        }
        if (!empty($body['carrier_name'])) {
            $order->carrier_name = $body['carrier_name'];
        }
        if (!empty($body['shipping_method'])) {
            $order->shipping_method_code = $body['shipping_method'];
        }
        if (!empty($body['shipped_at'])) {
            $order->shipped_at = $body['shipped_at'];
        } else {
            $order->shipped_at = now();
        }

        if ($order->getStatusEnum()->canTransitionTo(DropshipOrderStatus::SHIPPED)) {
            $order->status = DropshipOrderStatus::SHIPPED;
        }

        if (!empty($body['items'])) {
            foreach ($body['items'] as $itemData) {
                $item = $order->items()
                    ->where('sku', $itemData['sku'] ?? '')
                    ->first();
                if ($item && isset($itemData['shipped_quantity'])) {
                    $item->shipped_quantity = (int) $itemData['shipped_quantity'];
                    $item->save();
                }
            }
        }

        $order->save();

        $responseData = [
            'order_id' => $order->id,
            'tracking_no' => $order->tracking_no,
            'carrier_name' => $order->carrier_name,
            'shipped_at' => $order->shipped_at?->toDateTimeString(),
        ];

        return true;
    }

    private function processTrackingCallback(WmsCallbackLog $log, array $body, array &$responseData): bool
    {
        $order = $log->dropshipOrder;
        if (!$order) {
            $wmsOrderNo = $body['wms_order_no'] ?? $log->wms_order_no;
            $order = DropshipOrder::query()
                ->where('wms_order_no', $wmsOrderNo)
                ->orWhere('tracking_no', $body['tracking_no'] ?? '')
                ->first();
        }

        if (!$order) {
            throw new \RuntimeException('找不到关联的代发订单');
        }

        if (!empty($body['tracking_no']) && empty($order->tracking_no)) {
            $order->tracking_no = $body['tracking_no'];
        }
        if (!empty($body['carrier_name']) && empty($order->carrier_name)) {
            $order->carrier_name = $body['carrier_name'];
        }

        $events = $body['events'] ?? $body['tracking_history'] ?? [];
        if (!empty($events)) {
            $history = $order->tracking_history ?? [];
            foreach ($events as $event) {
                $order->addTrackingEvent(
                    $event['status'] ?? $event['code'] ?? 'update',
                    $event['location'] ?? '',
                    $event['description'] ?? $event['message'] ?? ''
                );
            }
        } else {
            $order->addTrackingEvent(
                $body['status'] ?? 'update',
                $body['location'] ?? '',
                $body['description'] ?? $body['message'] ?? '物流轨迹更新'
            );
        }

        $statusMap = [
            'in_transit' => DropshipOrderStatus::IN_TRANSIT,
            'customs' => DropshipOrderStatus::CUSTOMS,
            'delivered' => DropshipOrderStatus::DELIVERED,
            'returned' => DropshipOrderStatus::RETURNED,
        ];
        $trackStatus = $body['status'] ?? '';
        if (isset($statusMap[$trackStatus]) && $order->getStatusEnum()->canTransitionTo($statusMap[$trackStatus])) {
            $order->status = $statusMap[$trackStatus];
            $timestampField = $statusMap[$trackStatus]->timestampField();
            if ($timestampField !== null) {
                $order->{$timestampField} = now();
            }
        }

        $order->save();

        $responseData = [
            'order_id' => $order->id,
            'events_added' => count($events) ?: 1,
            'current_status' => $order->status,
        ];

        return true;
    }

    private function processInventoryCallback(WmsCallbackLog $log, array $body, array &$responseData): bool
    {
        $inventoryData = $body['inventory'] ?? $body['items'] ?? [];
        $updatedCount = 0;

        foreach ($inventoryData as $inv) {
            $sku = $inv['sku'] ?? $inv['product_sku'] ?? '';
            $quantity = $inv['quantity'] ?? $inv['stock'] ?? $inv['available'] ?? null;
            if (empty($sku) || $quantity === null) {
                continue;
            }
            $updatedCount++;
        }

        $responseData = [
            'warehouse_id' => $log->warehouse_id,
            'items_processed' => count($inventoryData),
            'items_updated' => $updatedCount,
        ];

        return true;
    }

    private function processStockAdjustCallback(WmsCallbackLog $log, array $body, array &$responseData): bool
    {
        $adjustments = $body['adjustments'] ?? [$body] ?? [];
        $count = count($adjustments);

        $responseData = [
            'warehouse_id' => $log->warehouse_id,
            'adjustments_count' => $count,
            'processed' => true,
        ];

        return true;
    }

    public function statistics(Request $request): JsonResponse
    {
        $baseQuery = WmsCallbackLog::query()
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('wms_provider'), fn ($q) => $q->byProvider($request->string('wms_provider')))
            ->when($request->filled('date_range'), function ($q) use ($request) {
                [$start, $end] = $request->input('date_range');
                $q->whereBetween('created_at', [$start, $end]);
            });

        $totalLogs = (clone $baseQuery)->count();
        $receivedCount = (clone $baseQuery)->where('status', 'received')->count();
        $processingCount = (clone $baseQuery)->where('status', 'processing')->count();
        $successCount = (clone $baseQuery)->success()->count();
        $failedCount = (clone $baseQuery)->failed()->count();
        $retryCount = (clone $baseQuery)->where('status', 'retry')->count();
        $pendingCount = (clone $baseQuery)->pending()->count();

        $typeStats = [];
        foreach (WmsCallbackType::cases() as $type) {
            $typeQuery = (clone $baseQuery)->byType($type);
            $typeStats[$type->value] = [
                'label' => $type->label(),
                'color' => $type->color(),
                'total' => (clone $typeQuery)->count(),
                'success' => (clone $typeQuery)->success()->count(),
                'failed' => (clone $typeQuery)->failed()->count(),
                'pending' => (clone $typeQuery)->pending()->count(),
            ];
        }

        $avgRetry = (clone $baseQuery)->avg('retry_count') ?? 0;
        $maxRetry = (clone $baseQuery)->max('retry_count') ?? 0;

        $today = (clone $baseQuery)->whereDate('created_at', today());
        $todayTotal = (clone $today)->count();
        $todaySuccess = (clone $today)->success()->count();
        $todayFailed = (clone $today)->failed()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalLogs,
                'received' => $receivedCount,
                'processing' => $processingCount,
                'success' => $successCount,
                'failed' => $failedCount,
                'retry' => $retryCount,
                'pending' => $pendingCount,
                'success_rate' => $totalLogs > 0 ? round(($successCount / $totalLogs) * 100, 2) . '%' : '0%',
                'average_retry_count' => round($avgRetry, 2),
                'max_retry_count' => (int) $maxRetry,
                'type_statistics' => $typeStats,
                'today' => [
                    'total' => $todayTotal,
                    'success' => $todaySuccess,
                    'failed' => $todayFailed,
                ],
            ],
        ]);
    }

    public function handleWmsCallback(Request $request, int $warehouseId): JsonResponse
    {
        $validated = $request->validate([
            'callback_type' => ['required', new Enum(WmsCallbackType::class)],
            'wms_order_no' => ['nullable', 'string', 'max:128'],
            'reference_no' => ['nullable', 'string', 'max:128'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', 'max:64'],
            'tracking_no' => ['nullable', 'string', 'max:128'],
            'carrier_name' => ['nullable', 'string', 'max:128'],
            'events' => ['nullable', 'array'],
            'inventory' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'adjustments' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
            'extra' => ['nullable', 'array'],
        ]);

        $provider = $request->input('wms_provider', $request->header('X-WMS-Provider', 'unknown'));
        $requestId = $validated['request_id'] ?? $request->header('X-Request-ID', 'req_' . uniqid('', true));

        $config = \App\Models\OverseaWarehouseConfig::query()
            ->where('warehouse_id', $warehouseId)
            ->first();

        $dropshipOrder = null;
        if (!empty($validated['wms_order_no'])) {
            $dropshipOrder = DropshipOrder::query()
                ->where('wms_order_no', $validated['wms_order_no'])
                ->first();
        }
        if (!$dropshipOrder && !empty($validated['tracking_no'])) {
            $dropshipOrder = DropshipOrder::query()
                ->where('tracking_no', $validated['tracking_no'])
                ->first();
        }

        $log = new WmsCallbackLog();
        $log->warehouse_id = $warehouseId;
        $log->wms_provider = $config?->wms_provider ?? $provider;
        $log->callback_type = $validated['callback_type'];
        $log->wms_order_no = $validated['wms_order_no'] ?? null;
        $log->dropship_order_id = $dropshipOrder?->id;
        $log->reference_no = $validated['reference_no'] ?? null;
        $log->request_id = $requestId;
        $log->status = 'received';
        $log->request_headers = json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE);
        $log->request_body = $request->getContent();
        $log->source_ip = $request->ip();
        $log->retry_count = 0;
        $log->extra_data = [
            'warehouse_config_exists' => $config !== null,
            'dropship_order_found' => $dropshipOrder !== null,
        ];
        $log->save();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '回调已接收',
            'data' => [
                'log_id' => $log->id,
                'request_id' => $requestId,
                'received_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}
