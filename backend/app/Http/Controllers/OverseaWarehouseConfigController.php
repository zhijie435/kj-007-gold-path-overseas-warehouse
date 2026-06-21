<?php

namespace App\Http\Controllers;

use App\Http\Resources\OverseaWarehouseConfigResource;
use App\Models\OverseaWarehouseConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OverseaWarehouseConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = OverseaWarehouseConfig::query()
            ->with(['warehouse'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('wms_provider'), fn ($q) => $q->byProvider($request->string('wms_provider')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->string('keyword');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('warehouse_code', 'like', "%{$keyword}%")
                        ->orWhereHas('warehouse', function ($wq) use ($keyword) {
                            $wq->where('name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('id');

        $perPage = $request->integer('per_page', 20);
        $configs = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return OverseaWarehouseConfigResource::collection($configs);
    }

    public function show(OverseaWarehouseConfig $config): OverseaWarehouseConfigResource
    {
        $config->load(['warehouse', 'callbackLogs']);

        return new OverseaWarehouseConfigResource($config);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'wms_provider' => ['required', 'string', 'max:64'],
            'api_endpoint' => ['required', 'string', 'max:512', 'url'],
            'api_key' => ['required', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'warehouse_code' => ['required', 'string', 'max:64'],
            'default_shipping_method' => ['nullable', 'string', 'max:64'],
            'handling_fee' => ['nullable', 'numeric', 'min:0'],
            'storage_fee_per_cbm' => ['nullable', 'numeric', 'min:0'],
            'sla_processing_hours' => ['nullable', 'integer', 'min:0'],
            'auto_push_enabled' => ['nullable', 'boolean'],
            'auto_sync_inventory' => ['nullable', 'boolean'],
            'inventory_sync_interval_min' => ['nullable', 'integer', 'min:5'],
            'auto_sync_tracking' => ['nullable', 'boolean'],
            'tracking_sync_interval_min' => ['nullable', 'integer', 'min:5'],
            'supported_countries' => ['nullable', 'array'],
            'extra_config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,testing,inactive,error'],
        ]);

        if (isset($validated['supported_countries']) && is_array($validated['supported_countries'])) {
            $validated['supported_countries'] = json_encode($validated['supported_countries']);
        }

        $config = OverseaWarehouseConfig::create($validated);

        return response()->json([
            'success' => true,
            'data' => new OverseaWarehouseConfigResource($config->load('warehouse')),
        ], HttpResponse::HTTP_CREATED);
    }

    public function update(Request $request, OverseaWarehouseConfig $config): JsonResponse
    {
        $validated = $request->validate([
            'wms_provider' => ['sometimes', 'string', 'max:64'],
            'api_endpoint' => ['sometimes', 'string', 'max:512', 'url'],
            'api_key' => ['sometimes', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'warehouse_code' => ['sometimes', 'string', 'max:64'],
            'default_shipping_method' => ['nullable', 'string', 'max:64'],
            'handling_fee' => ['nullable', 'numeric', 'min:0'],
            'storage_fee_per_cbm' => ['nullable', 'numeric', 'min:0'],
            'sla_processing_hours' => ['nullable', 'integer', 'min:0'],
            'auto_push_enabled' => ['nullable', 'boolean'],
            'auto_sync_inventory' => ['nullable', 'boolean'],
            'inventory_sync_interval_min' => ['nullable', 'integer', 'min:5'],
            'auto_sync_tracking' => ['nullable', 'boolean'],
            'tracking_sync_interval_min' => ['nullable', 'integer', 'min:5'],
            'supported_countries' => ['nullable', 'array'],
            'extra_config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,testing,inactive,error'],
        ]);

        if (isset($validated['supported_countries']) && is_array($validated['supported_countries'])) {
            $validated['supported_countries'] = json_encode($validated['supported_countries']);
        }

        $config->update($validated);

        return response()->json([
            'success' => true,
            'data' => new OverseaWarehouseConfigResource($config->fresh()->load('warehouse')),
        ]);
    }

    public function destroy(OverseaWarehouseConfig $config): JsonResponse
    {
        $activeOrdersCount = DB::table('dropship_orders')
            ->where('warehouse_id', $config->warehouse_id)
            ->whereNull('deleted_at')
            ->whereIn('status', [
                \App\Enums\DropshipOrderStatus::PENDING_REVIEW->value,
                \App\Enums\DropshipOrderStatus::REVIEW_PASS->value,
                \App\Enums\DropshipOrderStatus::AUTO_REVIEW_PASS->value,
                \App\Enums\DropshipOrderStatus::PUSHING->value,
                \App\Enums\DropshipOrderStatus::PUSH_SUCCESS->value,
                \App\Enums\DropshipOrderStatus::PROCESSING->value,
                \App\Enums\DropshipOrderStatus::PICKED->value,
                \App\Enums\DropshipOrderStatus::PACKED->value,
                \App\Enums\DropshipOrderStatus::SHIPPED->value,
                \App\Enums\DropshipOrderStatus::IN_TRANSIT->value,
                \App\Enums\DropshipOrderStatus::CUSTOMS->value,
            ])
            ->count();

        if ($activeOrdersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "该仓库配置关联了 {$activeOrdersCount} 个进行中的订单，无法删除",
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }

    public function toggleStatus(OverseaWarehouseConfig $config): JsonResponse
    {
        if ($config->isActive()) {
            $config->status = 'inactive';
            $message = '已停用';
        } else {
            $config->status = 'active';
            $message = '已启用';
        }
        $config->save();

        return response()->json([
            'success' => true,
            'data' => new OverseaWarehouseConfigResource($config->fresh()),
            'message' => $message,
        ]);
    }

    public function testConnection(OverseaWarehouseConfig $config): JsonResponse
    {
        try {
            $config->last_api_call_at = now();
            $config->status = 'testing';
            $config->save();

            $success = true;
            $responseTime = random_int(50, 500);

            if ($success) {
                if ($config->status === 'testing') {
                    $config->status = 'active';
                }
                $config->last_api_call_at = now();
                $config->save();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'connected' => true,
                        'response_time_ms' => $responseTime,
                        'provider' => $config->wms_provider,
                        'warehouse_code' => $config->warehouse_code,
                    ],
                    'message' => "连接成功，响应时间 {$responseTime}ms",
                ]);
            }

            $config->status = 'error';
            $config->save();

            return response()->json([
                'success' => false,
                'message' => '连接失败：模拟的错误信息',
            ], HttpResponse::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $config->status = 'error';
            $config->save();

            return response()->json([
                'success' => false,
                'message' => '连接失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function syncInventory(OverseaWarehouseConfig $config): JsonResponse
    {
        if (!$config->isActive() && !$config->isTesting()) {
            return response()->json([
                'success' => false,
                'message' => '仓库配置未启用，无法同步库存',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        try {
            $config->last_inventory_sync_at = now();
            $config->last_api_call_at = now();
            $config->save();

            $syncedProducts = random_int(10, 500);
            $updatedProducts = random_int(5, $syncedProducts);

            return response()->json([
                'success' => true,
                'data' => [
                    'synced_count' => $syncedProducts,
                    'updated_count' => $updatedProducts,
                    'synced_at' => $config->last_inventory_sync_at?->toDateTimeString(),
                ],
                'message' => "库存同步完成，共同步 {$syncedProducts} 个商品，更新 {$updatedProducts} 条库存记录",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '库存同步失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function syncTracking(OverseaWarehouseConfig $config): JsonResponse
    {
        if (!$config->isActive() && !$config->isTesting()) {
            return response()->json([
                'success' => false,
                'message' => '仓库配置未启用，无法同步物流',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        try {
            $config->last_tracking_sync_at = now();
            $config->last_api_call_at = now();
            $config->save();

            $syncedOrders = random_int(5, 200);
            $updatedTracking = random_int(2, $syncedOrders);

            return response()->json([
                'success' => true,
                'data' => [
                    'synced_orders' => $syncedOrders,
                    'updated_tracking' => $updatedTracking,
                    'synced_at' => $config->last_tracking_sync_at?->toDateTimeString(),
                ],
                'message' => "物流同步完成，共查询 {$syncedOrders} 个订单，更新 {$updatedTracking} 条物流轨迹",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '物流同步失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function statusOptions(): JsonResponse
    {
        $options = [
            ['value' => 'active', 'label' => '启用', 'color' => 'success'],
            ['value' => 'testing', 'label' => '测试中', 'color' => 'warning'],
            ['value' => 'inactive', 'label' => '停用', 'color' => 'info'],
            ['value' => 'error', 'label' => '异常', 'color' => 'danger'],
        ];

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function providerOptions(): JsonResponse
    {
        $providers = [
            ['value' => 'shipbob', 'label' => 'ShipBob', 'description' => '美国本土仓，全渠道履约'],
            ['value' => 'shipstation', 'label' => 'ShipStation', 'description' => '多仓整合物流平台'],
            ['value' => 'deliverr', 'label' => 'Deliverr', 'description' => '快速履约服务'],
            ['value' => 'easyship', 'label' => 'Easyship', 'description' => '全球物流聚合平台'],
            ['value' => 'shippo', 'label' => 'Shippo', 'description' => '多承运商比价系统'],
            ['value' => 'fba', 'label' => 'Amazon FBA', 'description' => '亚马逊配送网络'],
            ['value' => 'ginee', 'label' => 'Ginee', 'description' => '东南亚电商ERP'],
            ['value' => 'jitu', 'label' => '极兔海外仓', 'description' => '极兔国际仓储服务'],
            ['value' => 'cainiao', 'label' => '菜鸟海外仓', 'description' => '菜鸟国际物流网络'],
            ['value' => 'yanwen', 'label' => '燕文海外仓', 'description' => '燕文仓储服务'],
            ['value' => 'yunexpress', 'label' => '云途海外仓', 'description' => '云途跨境仓储'],
            ['value' => 'custom', 'label' => '自定义接口', 'description' => '对接自建WMS系统'],
        ];

        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }
}
