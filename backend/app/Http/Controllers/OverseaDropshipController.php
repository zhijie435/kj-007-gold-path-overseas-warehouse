<?php

namespace App\Http\Controllers;

use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Http\Resources\DropshipOrderResource;
use App\Models\DropshipOrder;
use App\Services\AutomationEngineService;
use App\Services\DropshipPermissionService;
use App\Services\DropshipQueryService;
use App\Services\DropshipStateMachine;
use App\Services\OverseaDropshipService;
use App\Services\WmsIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OverseaDropshipController extends Controller
{
    public function __construct(
        protected DropshipQueryService $queryService,
        protected DropshipPermissionService $permissionService,
        protected DropshipStateMachine $stateMachine,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW);

        $query = $this->queryService->buildOrderQuery($request);
        $query = $this->permissionService->applyDataScopeQuery($query, $request->user());
        $orders = $this->queryService->paginateOrders($query, $request);

        return DropshipOrderResource::collection($orders);
    }

    public function show(DropshipOrder $order, Request $request): DropshipOrderResource
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW, $order);

        $order->load(['items', 'warehouse', 'supplier', 'distributor', 'creator', 'reviewer', 'callbackLogs']);

        return new DropshipOrderResource($order);
    }

    public function store(Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_CREATE);

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
            return DB::transaction(function () use ($validated, $request): JsonResponse {
                $submitNow = $validated['submit_now'] ?? false;
                unset($validated['submit_now']);

                $service = app(OverseaDropshipService::class);
                $order = $service->createDropshipOrder($validated, $request->user());

                $automationService = app(AutomationEngineService::class);
                $automationService->executeRulesForOrder($order, 'order_created');

                if ($submitNow) {
                    $order->refresh();
                    $currentStatus = $order->getStatusEnum();
                    if ($currentStatus === DropshipOrderStatus::DRAFT) {
                        $order = $service->updateDropshipStatus($order, DropshipOrderStatus::PENDING_REVIEW, [
                            'source' => 'auto_submit',
                        ]);
                    }
                    $order->refresh();
                    $currentStatus = $order->getStatusEnum();
                    if (in_array($currentStatus, [DropshipOrderStatus::PENDING_REVIEW, DropshipOrderStatus::DRAFT], true)) {
                        $automationService->executeRulesForOrder($order, 'order_submitted');
                    }
                    $order->refresh();
                    $currentStatus = $order->getStatusEnum();
                    if (in_array($currentStatus, [DropshipOrderStatus::AUTO_REVIEW_PASS, DropshipOrderStatus::REVIEW_PASS], true)) {
                        $automationService->executeRulesForOrder($order, 'review_passed');
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => new DropshipOrderResource($order->load('items')),
                ], HttpResponse::HTTP_CREATED);
            });
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, DropshipOrder $order): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_EDIT, $order);

        try {
            $this->stateMachine->ensureCanEdit($order);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        }

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

        $order->update($validated);

        return response()->json([
            'success' => true,
            'data' => new DropshipOrderResource($order->fresh()),
        ]);
    }

    public function destroy(DropshipOrder $order, Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_DELETE, $order);

        try {
            $this->stateMachine->ensureCanDelete($order);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->queryService->getOrderStatistics($request),
        ]);
    }

    public function batchReview(Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_REVIEW);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dropship_orders,id'],
            'pass' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        $service = app(OverseaDropshipService::class);
        $automationService = app(AutomationEngineService::class);
        $user = $request->user();

        $orders = DropshipOrder::query()
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', $this->stateMachine->getReviewableStatusValues())
            ->get();

        $successCount = 0;
        $failed = [];

        DB::transaction(function () use ($orders, $validated, $service, $automationService, $user, &$successCount, &$failed) {
            foreach ($orders as $order) {
                try {
                    $this->permissionService->ensureCan($user, DropshipPermissionService::ACTION_REVIEW, $order);

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
                } catch (DropshipException $e) {
                    $failed[] = [
                        'id' => $order->id,
                        'dropship_no' => $order->dropship_no,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getErrorCode(),
                    ];
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
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_REVIEW, $order);

        $validated = $request->validate([
            'pass' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(OverseaDropshipService::class);
            $order = $service->reviewOrder(
                $order,
                $validated['pass'],
                $validated['remark'] ?? null,
                $request->user()
            );

            if ($validated['pass']) {
                $automationService = app(AutomationEngineService::class);
                $automationService->executeRulesForOrder($order, 'review_passed');
            }

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => $validated['pass'] ? '审核通过' : '审核拒绝',
            ]);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function push(DropshipOrder $order, Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_PUSH, $order);

        try {
            $service = app(OverseaDropshipService::class);
            $order = $service->pushToWms($order);

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => '推单成功',
            ]);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                new DropshipException('推单失败：' . $e->getMessage(), DropshipException::WMS_API_ERROR),
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function batchPush(Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_PUSH);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dropship_orders,id'],
        ]);

        $orders = DropshipOrder::query()
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', $this->stateMachine->getPushableStatusValues())
            ->get();

        $service = app(OverseaDropshipService::class);
        $user = $request->user();

        $successCount = 0;
        $failed = [];

        foreach ($orders as $order) {
            try {
                $this->permissionService->ensureCan($user, DropshipPermissionService::ACTION_PUSH, $order);
                $service->pushToWms($order);
                $successCount++;
            } catch (DropshipException $e) {
                $failed[] = [
                    'id' => $order->id,
                    'dropship_no' => $order->dropship_no,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                ];
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
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_UPDATE_STATUS, $order);

        $validated = $request->validate([
            'status' => ['required', new Enum(DropshipOrderStatus::class)],
            'remark' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(OverseaDropshipService::class);
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
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function cancel(Request $request, DropshipOrder $order): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_CANCEL, $order);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $service = app(OverseaDropshipService::class);
            $order = $service->cancelOrder($order, $validated['reason'] ?? '用户取消', $request->user());

            return response()->json([
                'success' => true,
                'data' => new DropshipOrderResource($order->fresh()),
                'message' => '订单已取消',
            ]);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function retryPush(DropshipOrder $order, Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_PUSH, $order);

        try {
            $this->stateMachine->ensureCanRetryPush($order);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        }

        return $this->push($order, $request);
    }

    public function syncTracking(DropshipOrder $order, Request $request): JsonResponse
    {
        $this->permissionService->ensureCan($request->user(), DropshipPermissionService::ACTION_SYNC_TRACKING, $order);

        try {
            if (empty($order->warehouse_id) || empty($order->wms_order_no)) {
                throw new DropshipException(
                    '缺少仓库或WMS单号，无法同步物流轨迹',
                    DropshipException::WAREHOUSE_NOT_ASSIGNED
                );
            }

            $wmsService = app(WmsIntegrationService::class);
            $config = \App\Models\OverseaWarehouseConfig::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->active()
                ->first();

            if ($config === null) {
                throw DropshipException::warehouseConfigInvalid();
            }

            $trackingEvents = $wmsService->fetchTracking($config, $order);

            return response()->json([
                'success' => true,
                'data' => [
                    'tracking_events' => $trackingEvents,
                ],
                'message' => '物流轨迹同步成功',
            ]);
        } catch (DropshipException $e) {
            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                new DropshipException('物流轨迹同步失败：' . $e->getMessage(), DropshipException::WMS_API_ERROR),
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
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

    protected function errorResponse(\Throwable $e, int $statusCode = HttpResponse::HTTP_BAD_REQUEST): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $e->getMessage(),
        ];

        if ($e instanceof DropshipException) {
            $data['error_code'] = $e->getErrorCode();
            if (!empty($e->getContext())) {
                $data['context'] = $e->getContext();
            }
        }

        return response()->json($data, $statusCode);
    }
}
