<?php

namespace App\Services;

use App\Enums\DropshipOrderStatus;
use App\Enums\WmsCallbackType;
use App\Models\DropshipOrder;
use App\Models\WmsCallbackLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DropshipQueryService
{
    public function buildOrderQuery(Request $request): Builder
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

        return $query;
    }

    public function paginateOrders(Builder $query, Request $request): mixed
    {
        $perPage = $request->integer('per_page', 20);
        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function getOrderStatistics(Request $request): array
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
        $pendingReview = (clone $baseQuery)->byStatus(DropshipOrderStatus::PENDING_REVIEW)->count();
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

        return [
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
        ];
    }

    public function getStatusSummary(): array
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

    public function buildCallbackLogQuery(Request $request): Builder
    {
        return WmsCallbackLog::query()
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
    }

    public function getCallbackLogStatistics(Request $request): array
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

        return [
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
        ];
    }
}
