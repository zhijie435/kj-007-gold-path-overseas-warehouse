<?php

namespace App\Http\Controllers;

use App\Enums\DropshipOrderStatus;
use App\Http\Resources\DropshipOrderResource;
use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OverseaDropshipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DropshipOrder::query()
            ->with(['items', 'warehouse', 'creator', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->byStatus($request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('source_channel'), fn ($q) => $q->byChannel($request->string('source_channel')))
            ->when($request->filled('receiver_country'), fn ($q) => $q->byCountry($request->string('receiver_country')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->string('keyword');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('dropship_no', 'like', "%{$keyword}%")
                        ->orWhere('external_order_no', 'like', "%{$keyword}%")
                        ->orWhere('wms_order_no', 'like', "%{$keyword}%")
                        ->orWhere('tracking_no', 'like', "%{$keyword}%")
                        ->orWhere('receiver_name', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('date_range'), function ($q) use ($request) {
                [$start, $end] = $request->input('date_range');
                $q->whereBetween('created_at', [$start, $end]);
            })
            ->orderByDesc('id');

        $perPage = $request->integer('per_page', 20);
        $orders = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return DropshipOrderResource::collection($orders);
    }

    public function show(DropshipOrder $order): DropshipOrderResource
    {
        $order->load(['items', 'warehouse', 'supplier', 'distributor', 'creator', 'reviewer', 'callbackLogs']);

        return new DropshipOrderResource($order);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'external_order_no' => ['nullable', 'string', 'max:64'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'distributor_id' => ['nullable', 'integer', 'exists:distributors,id'],
            'source_channel' => ['required', 'string', 'max:32'],
            'fulfillment_type' => ['nullable', 'string', 'max:32'],
            'shipping_method_code' => ['nullable', 'string', 'max:64'],
            'receiver_name' => ['required', 'string', 'max:128'],
            'receiver_phone' => ['required', 'string', 'max:64'],
            'receiver_email' => ['nullable', 'email', 'max:128'],
            'receiver_country' => ['required', 'string', 'max:8'],
            'receiver_state' => ['nullable', 'string', 'max:64'],
            'receiver_city' => ['nullable', 'string', 'max:64'],
            'receiver_postal_code' => ['nullable', 'string', 'max:32'],
            'receiver_address' => ['required', 'string', 'max:512'],
            'currency' => ['nullable', 'string', 'max:8'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:1024'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:16'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.hs_code' => ['nullable', 'string', 'max:32'],
            'items.*.remark' => ['nullable', 'string', 'max:512'],
            'submit_now' => ['nullable', 'boolean'],
        ]);

        try {
            $submitNow = $validated['submit_now'] ?? false;
            unset($validated['submit_now']);

            $service = app(\App\Services\OverseaDropshipService::class);
            $order = $service->createDropshipOrder($validated, $request->user());

            $automationService = app(\App\Services\AutomationEngineService::class);
            $automationService->executeRulesForOrder($order, 'order_created');

            if ($submitNow) {
                $order = $service->updateDropshipStatus($order, DropshipOrderStatus::PENDING_REVIEW, [
                    'source' => 'auto_submit',
                ]);
                $automationService->executeRulesForOrder($order, 'order_submitted');
            }

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->load('items')),
            ], HttpResponse::HTTP_CREATED);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request, DropshipOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'distributor_id' => ['nullable', 'integer', 'exists:distributors,id'],
            'source_channel' => ['sometimes', 'string', 'max:32'],
            'fulfillment_type' => ['nullable', 'string', 'max:32'],
            'shipping_method_code' => ['nullable', 'string', 'max:64'],
            'receiver_name' => ['sometimes', 'string', 'max:128'],
            'receiver_phone' => ['sometimes', 'string', 'max:64'],
            'receiver_email' => ['nullable', 'email', 'max:128'],
            'receiver_country' => ['sometimes', 'string', 'max:8'],
            'receiver_state' => ['nullable', 'string', 'max:64'],
            'receiver_city' => ['nullable', 'string', 'max:64'],
            'receiver_postal_code' => ['nullable', 'string', 'max:32'],
            'receiver_address' => ['sometimes', 'string', 'max:512'],
            'currency' => ['nullable', 'string', 'max:8'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        if ($order->getStatusEnum()->isTerminal()) {
            return response()->json([
                'success' => false,
                'message' => '订单已处于终态，无法修改',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        $order->update($validated);

        return response()->json([
            'success' => true,
            'data' => new DropshipOrderResource($order->fresh()),
        ]);
    }

    public function destroy(DropshipOrder $order): JsonResponse
    {
        if (!$order->getStatusEnum()->isTerminal()) {
            return response()->json([
                'success' => false,
                'message' => '订单未处于终态，无法删除',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $baseQuery = DropshipOrder::query()
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('date_range'), function ($q) use ($request) {
                [$start, $end] = $request->input('date_range');
                $q->whereBetween('created_at', [$start, $end]);
            });

        $statusCounts = [];
        foreach (DropshipOrderStatus::cases() as $status) {
            $statusCounts[$status->value] = (clone $baseQuery)->byStatus($status)->count();
        }

        $totalOrders = (clone $baseQuery)->count();
        $totalCost = (clone $baseQuery)->sum('total_cost');
        $pendingReview = (clone $baseQuery)->whereIn('status', [
            DropshipOrderStatus::PENDING_REVIEW->value,
        ])->count();
        $pendingPush = (clone $baseQuery)->pendingPush()->count();
        $inFulfillment = (clone $baseQuery)->inFulfillment()->count();
        $inTransit = (clone $baseQuery)->inTransit()->count();
        $completed = (clone $baseQuery)->byStatus(DropshipOrderStatus::COMPLETED)->count();
        $cancelled = (clone $baseQuery)->byStatus(DropshipOrderStatus::CANCELLED)->count();
        $exceptions = (clone $baseQuery)->byStatus(DropshipOrderStatus::EXCEPTION)->count();

        $pushFailed = (clone $baseQuery)->byStatus(DropshipOrderStatus::PUSH_FAILED)->count();

        $todayOrders = (clone $baseQuery)->whereDate('created_at', today())->count();
        $todayShipped = (clone $baseQuery)->whereDate('shipped_at', today())->count();
        $todayDelivered = (clone $baseQuery)->whereDate('delivered_at', today())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'total_cost' => round($totalCost, 2),
                'status_counts' => $statusCounts,
                'pending_review' => $pendingReview,
                'pending_push' => $pendingPush,
                'in_fulfillment' => $inFulfillment,
                'in_transit' => $inTransit,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'exceptions' => $exceptions,
                'push_failed' => $pushFailed,
                'completion_rate' => $totalOrders > 0 ? round(($completed / $totalOrders) * 100, 2) : 0,
                'today' => [
                    'orders' => $todayOrders,
                    'shipped' => $todayShipped,
                    'delivered' => $todayDelivered,
                ],
            ],
        ]);
    }

    public function batchReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dropship_orders,id'],
            'pass' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        $service = app(\App\Services\OverseaDropshipService::class);
        $automationService = app(\App\Services\AutomationEngineService::class);
        $user = $request->user();

        $orders = DropshipOrder::query()
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', [DropshipOrderStatus::PENDING_REVIEW->value, DropshipOrderStatus::DRAFT->value])
            ->get();

        $successCount = 0;
        $failed = [];

        DB::transaction(function () use ($orders, $validated, $service, $automationService, $user, &$successCount, &$failed) {
            foreach ($orders as $order) {
                try {
                    $service->reviewOrder(
                        $order,
                        $validated['pass'],
                        $validated['remark'] ?? null,
                        $user
                    );

                    if ($validated['pass']) {
                        $automationService->executeRulesForOrder($order, 'review_passed');
                    }

                    $successCount++;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => $order->id,
                        'dropship_no' => $order->dropship_no,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => [
                'success_count' => $successCount,
                'failed_count' => count($failed),
                'failed' => $failed,
            ],
            'message' => "批量审核完成，成功 {$successCount} 条",
        ]);
    }

    public function review(Request $request, DropshipOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'pass' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(\App\Services\OverseaDropshipService::class);
            $order = $service->reviewOrder(
                $order,
                $validated['pass'],
                $validated['remark'] ?? null,
                $request->user()
            );

            $automationService = app(\App\Services\AutomationEngineService::class);
            $automationService->executeRulesForOrder($order, 'review_passed');

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => $validated['pass'] ? '审核通过' : '审核拒绝',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_BAD_REQUEST);
        }
    }

    public function push(DropshipOrder $order): JsonResponse
    {
        try {
            $service = app(\App\Services\OverseaDropshipService::class);
            $order = $service->pushToWms($order);

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => '推单成功',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '推单失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function batchPush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dropship_orders,id'],
        ]);

        $allowedStatuses = [
            DropshipOrderStatus::REVIEW_PASS->value,
            DropshipOrderStatus::AUTO_REVIEW_PASS->value,
            DropshipOrderStatus::PUSH_FAILED->value,
        ];

        $orders = DropshipOrder::query()
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', $allowedStatuses)
            ->get();

        $service = app(\App\Services\OverseaDropshipService::class);

        $successCount = 0;
        $failed = [];

        foreach ($orders as $order) {
            try {
                $service->pushToWms($order);
                $successCount++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'id' => $order->id,
                    'dropship_no' => $order->dropship_no,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'success_count' => $successCount,
                'failed_count' => count($failed),
                'failed' => $failed,
            ],
            'message' => "批量推单完成，成功 {$successCount} 条",
        ]);
    }

    public function updateStatus(Request $request, DropshipOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(DropshipOrderStatus::class)],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(\App\Services\OverseaDropshipService::class);
            $context = [];
            if (!empty($validated['remark'])) {
                $context['remark'] = $validated['remark'];
            }
            $order = $service->updateDropshipStatus($order, $validated['status'], $context);

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => '状态更新成功',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(Request $request, DropshipOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(\App\Services\OverseaDropshipService::class);
            $order = $service->cancelOrder($order, $validated['reason'] ?? '用户取消');

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => '订单已取消',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_BAD_REQUEST);
        }
    }

    public function retryPush(DropshipOrder $order): JsonResponse
    {
        if ($order->getStatusEnum() !== DropshipOrderStatus::PUSH_FAILED) {
            return response()->json([
                'success' => false,
                'message' => '只有推单失败状态才能重试推单',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        return $this->push($order);
    }

    public function statusOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DropshipOrderStatus::options(),
        ]);
    }

    public function channelOptions(): JsonResponse
    {
        $channels = [
            ['value' => 'shopify', 'label' => 'Shopify'],
            ['value' => 'amazon', 'label' => 'Amazon'],
            ['value' => 'ebay', 'label' => 'eBay'],
            ['value' => 'tiktok', 'label' => 'TikTok Shop'],
            ['value' => 'lazada', 'label' => 'Lazada'],
            ['value' => 'shopee', 'label' => 'Shopee'],
            ['value' => 'manual', 'label' => '手动创建'],
            ['value' => 'api', 'label' => 'API对接'],
            ['value' => 'other', 'label' => '其他'],
        ];

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }
}
