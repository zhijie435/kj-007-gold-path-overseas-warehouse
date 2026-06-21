<?php

namespace App\Http\Controllers;

use App\Enums\DropshipOrderStatus;
use App\Enums\WmsCallbackType;
use App\Http\Resources\WmsCallbackLogResource;
use App\Models\DropshipOrder;
use App\Models\WmsCallbackLog;
use App\Services\DropshipPermissionService;
use App\Services\DropshipQueryService;
use App\Services\DropshipStateMachine;
use App\Services\WmsIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WmsCallbackLogController extends Controller
{
    public function __construct(
        protected DropshipQueryService $queryService,
        protected DropshipPermissionService $permissionService,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW);

        $query = $this->queryService->buildCallbackLogQuery($request);
        $logs = $this->queryService->paginateOrders($query, $request);

        return WmsCallbackLogResource::collection($logs);
    }

    public function show(WmsCallbackLog $log, Request $request): WmsCallbackLogResource
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW);

        $log->load(['warehouse', 'dropshipOrder', 'processor']);

        return new WmsCallbackLogResource($log);
    }

    public function retry(WmsCallbackLog $log, Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_UPDATE_STATUS);

        if (!$log->isFailed() && !$log->isRetry()) {
            return response()->json([
                'success' => false,
                'message' => '只有失败或重试状态的日志才能重新处理',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        try {
            $wmsService = app(WmsIntegrationService::class);
            $wmsService->handleCallback($log);

            $log->processed_by = $request->user()?->id;
            $log->save();

            return response()->json([
                'success' => true,
                'data' => new WmsCallbackLogResource($log->fresh()),
                'message' => '回调处理成功',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '回调处理失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->queryService->getCallbackLogStatistics($request),
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

        try {
            \App\Jobs\ProcessWmsCallbackJob::dispatch($log);
        } catch (\Throwable $e) {
            report($e);
        }

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
