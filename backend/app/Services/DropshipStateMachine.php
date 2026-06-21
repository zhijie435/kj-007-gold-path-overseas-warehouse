<?php

namespace App\Services;

use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use Illuminate\Support\Facades\DB;

class DropshipStateMachine
{
    public function canReview(DropshipOrder $order): bool
    {
        return in_array($order->getStatusEnum(), [
            DropshipOrderStatus::DRAFT,
            DropshipOrderStatus::PENDING_REVIEW,
        ], true);
    }

    public function canPushToWms(DropshipOrder $order): bool
    {
        return in_array($order->getStatusEnum(), [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
        ], true);
    }

    public function canCancel(DropshipOrder $order): bool
    {
        return !$order->getStatusEnum()->isTerminal();
    }

    public function canEdit(DropshipOrder $order): bool
    {
        return !$order->getStatusEnum()->isTerminal()
            && !in_array($order->getStatusEnum(), [
                DropshipOrderStatus::PUSHING,
                DropshipOrderStatus::PROCESSING,
                DropshipOrderStatus::PICKED,
                DropshipOrderStatus::PACKED,
                DropshipOrderStatus::SHIPPED,
                DropshipOrderStatus::IN_TRANSIT,
                DropshipOrderStatus::CUSTOMS,
            ], true);
    }

    public function canDelete(DropshipOrder $order): bool
    {
        return $order->getStatusEnum()->isTerminal();
    }

    public function canRetryPush(DropshipOrder $order): bool
    {
        return $order->getStatusEnum() === DropshipOrderStatus::PUSH_FAILED;
    }

    public function canSyncTracking(DropshipOrder $order): bool
    {
        return !empty($order->warehouse_id) && !empty($order->wms_order_no);
    }

    public function ensureCanReview(DropshipOrder $order): void
    {
        if (!$this->canReview($order)) {
            throw new DropshipException(
                sprintf('当前状态 [%s] 不允许审核操作', $order->getStatusEnum()->label()),
                DropshipException::INVALID_STATUS_TRANSITION,
                ['current_status' => $order->getStatusEnum()->value, 'action' => 'review']
            );
        }
    }

    public function ensureCanPushToWms(DropshipOrder $order): void
    {
        if (!$this->canPushToWms($order)) {
            throw new DropshipException(
                sprintf('当前状态 [%s] 不允许推送到WMS', $order->getStatusEnum()->label()),
                DropshipException::INVALID_STATUS_TRANSITION,
                ['current_status' => $order->getStatusEnum()->value, 'action' => 'push']
            );
        }
    }

    public function ensureCanCancel(DropshipOrder $order): void
    {
        if (!$this->canCancel($order)) {
            throw DropshipException::orderTerminal(
                $order->getStatusEnum()->label(),
                ['action' => 'cancel']
            );
        }
    }

    public function ensureCanEdit(DropshipOrder $order): void
    {
        if (!$this->canEdit($order)) {
            throw new DropshipException(
                sprintf('当前状态 [%s] 不允许修改', $order->getStatusEnum()->label()),
                DropshipException::INVALID_STATUS_TRANSITION,
                ['current_status' => $order->getStatusEnum()->value, 'action' => 'edit']
            );
        }
    }

    public function ensureCanDelete(DropshipOrder $order): void
    {
        if (!$this->canDelete($order)) {
            throw new DropshipException(
                '订单未处于终态，无法删除',
                DropshipException::INVALID_STATUS_TRANSITION,
                ['current_status' => $order->getStatusEnum()->value, 'action' => 'delete']
            );
        }
    }

    public function ensureCanRetryPush(DropshipOrder $order): void
    {
        if (!$this->canRetryPush($order)) {
            throw new DropshipException(
                '只有推单失败状态才能重试推单',
                DropshipException::INVALID_STATUS_TRANSITION,
                ['current_status' => $order->getStatusEnum()->value, 'action' => 'retry_push']
            );
        }
    }

    public function ensureCanTransition(DropshipOrder $order, DropshipOrderStatus $target): void
    {
        $current = $order->getStatusEnum();
        if (!$current->canTransitionTo($target)) {
            throw DropshipException::invalidStatusTransition(
                $current->label(),
                $target->label(),
                ['current_status' => $current->value, 'target_status' => $target->value]
            );
        }
    }

    public function transition(DropshipOrder $order, DropshipOrderStatus $target, array $context = []): DropshipOrder
    {
        return DB::transaction(function () use ($order, $target, $context): DropshipOrder {
            $current = $order->getStatusEnum();

            $this->ensureCanTransition($order, $target);

            $order->status = $target;

            $timestampField = $target->timestampField();
            if ($timestampField !== null && empty($order->{$timestampField})) {
                $order->{$timestampField} = now();
            }

            if (!empty($context)) {
                $extraData = $order->extra_data ?? [];
                $extraData['status_context'] = [
                    'from' => $current->value,
                    'to' => $target->value,
                    'time' => now()->toDateTimeString(),
                    'context' => $context,
                ];
                $order->extra_data = $extraData;
            }

            $order->save();

            return $order;
        });
    }

    public function getPushableStatuses(): array
    {
        return [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
        ];
    }

    public function getPushableStatusValues(): array
    {
        return array_map(fn ($s) => $s->value, $this->getPushableStatuses());
    }

    public function getReviewableStatuses(): array
    {
        return [
            DropshipOrderStatus::DRAFT,
            DropshipOrderStatus::PENDING_REVIEW,
        ];
    }

    public function getReviewableStatusValues(): array
    {
        return array_map(fn ($s) => $s->value, $this->getReviewableStatuses());
    }

    public function mapWmsStatus(string $wmsStatus): ?DropshipOrderStatus
    {
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
            'cancelled' => DropshipOrderStatus::CANCELLED,
            'returned' => DropshipOrderStatus::RETURNED,
            'exception' => DropshipOrderStatus::EXCEPTION,
        ];

        return $statusMap[strtolower($wmsStatus)] ?? null;
    }
}
