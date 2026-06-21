<?php

namespace App\Enums;

enum DropshipOrderStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case AUTO_REVIEW_PASS = 'auto_review_pass';
    case REVIEW_PASS = 'review_pass';
    case REVIEW_REJECT = 'review_reject';
    case PUSHING = 'pushing';
    case PUSH_SUCCESS = 'push_success';
    case PUSH_FAILED = 'push_failed';
    case PROCESSING = 'processing';
    case PICKED = 'picked';
    case PACKED = 'packed';
    case SHIPPED = 'shipped';
    case IN_TRANSIT = 'in_transit';
    case CUSTOMS = 'customs';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
    case EXCEPTION = 'exception';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '草稿',
            self::PENDING_REVIEW => '待审核',
            self::AUTO_REVIEW_PASS => '自动审核通过',
            self::REVIEW_PASS => '审核通过',
            self::REVIEW_REJECT => '审核拒绝',
            self::PUSHING => '推单中',
            self::PUSH_SUCCESS => '推单成功',
            self::PUSH_FAILED => '推单失败',
            self::PROCESSING => '处理中',
            self::PICKED => '已拣货',
            self::PACKED => '已打包',
            self::SHIPPED => '已发货',
            self::IN_TRANSIT => '运输中',
            self::CUSTOMS => '清关中',
            self::DELIVERED => '已签收',
            self::COMPLETED => '已完成',
            self::CANCELLED => '已取消',
            self::RETURNED => '已退回',
            self::EXCEPTION => '异常',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'info',
            self::PENDING_REVIEW => 'warning',
            self::AUTO_REVIEW_PASS => 'success',
            self::REVIEW_PASS => 'success',
            self::REVIEW_REJECT => 'danger',
            self::PUSHING => 'primary',
            self::PUSH_SUCCESS => 'success',
            self::PUSH_FAILED => 'danger',
            self::PROCESSING => 'primary',
            self::PICKED => 'primary',
            self::PACKED => 'primary',
            self::SHIPPED => 'success',
            self::IN_TRANSIT => 'warning',
            self::CUSTOMS => 'warning',
            self::DELIVERED => 'success',
            self::COMPLETED => 'success',
            self::CANCELLED => 'info',
            self::RETURNED => 'warning',
            self::EXCEPTION => 'danger',
        };
    }

    public function timestampField(): ?string
    {
        return match ($this) {
            self::REVIEW_PASS, self::REVIEW_REJECT, self::AUTO_REVIEW_PASS => 'reviewed_at',
            self::PUSH_SUCCESS => 'pushed_at',
            self::SHIPPED => 'shipped_at',
            self::DELIVERED => 'delivered_at',
            self::COMPLETED => 'completed_at',
            self::CANCELLED => 'cancelled_at',
            default => null,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::RETURNED,
            self::REVIEW_REJECT,
        ], true);
    }

    public function canTransitionTo(self $target): bool
    {
        $transitions = [
            self::DRAFT->value => [self::PENDING_REVIEW, self::CANCELLED],
            self::PENDING_REVIEW->value => [self::AUTO_REVIEW_PASS, self::REVIEW_PASS, self::REVIEW_REJECT, self::CANCELLED],
            self::AUTO_REVIEW_PASS->value => [self::PUSHING, self::CANCELLED],
            self::REVIEW_PASS->value => [self::PUSHING, self::CANCELLED],
            self::PUSHING->value => [self::PUSH_SUCCESS, self::PUSH_FAILED, self::EXCEPTION],
            self::PUSH_FAILED->value => [self::PUSHING, self::CANCELLED, self::EXCEPTION],
            self::PUSH_SUCCESS->value => [self::PROCESSING, self::EXCEPTION],
            self::PROCESSING->value => [self::PICKED, self::EXCEPTION, self::CANCELLED],
            self::PICKED->value => [self::PACKED, self::EXCEPTION],
            self::PACKED->value => [self::SHIPPED, self::EXCEPTION],
            self::SHIPPED->value => [self::IN_TRANSIT, self::CUSTOMS, self::DELIVERED, self::RETURNED, self::EXCEPTION],
            self::IN_TRANSIT->value => [self::CUSTOMS, self::DELIVERED, self::RETURNED, self::EXCEPTION],
            self::CUSTOMS->value => [self::IN_TRANSIT, self::DELIVERED, self::EXCEPTION],
            self::DELIVERED->value => [self::COMPLETED, self::RETURNED, self::EXCEPTION],
            self::COMPLETED->value => [],
            self::CANCELLED->value => [],
            self::RETURNED->value => [],
            self::REVIEW_REJECT->value => [],
            self::EXCEPTION->value => [self::PROCESSING, self::CANCELLED, self::PUSHING],
        ];

        return in_array($target->value, $transitions[$this->value] ?? [], true);
    }

    public function allowedTransitions(): array
    {
        $transitions = [
            self::DRAFT->value => [self::PENDING_REVIEW, self::CANCELLED],
            self::PENDING_REVIEW->value => [self::REVIEW_PASS, self::REVIEW_REJECT, self::CANCELLED],
            self::AUTO_REVIEW_PASS->value => [self::PUSHING, self::CANCELLED],
            self::REVIEW_PASS->value => [self::PUSHING, self::CANCELLED],
            self::PUSHING->value => [self::PUSH_SUCCESS, self::PUSH_FAILED],
            self::PUSH_FAILED->value => [self::PUSHING, self::CANCELLED],
            self::PUSH_SUCCESS->value => [self::PROCESSING],
            self::PROCESSING->value => [self::PICKED, self::CANCELLED],
            self::PICKED->value => [self::PACKED],
            self::PACKED->value => [self::SHIPPED],
            self::SHIPPED->value => [self::IN_TRANSIT, self::CUSTOMS, self::DELIVERED],
            self::IN_TRANSIT->value => [self::CUSTOMS, self::DELIVERED],
            self::CUSTOMS->value => [self::IN_TRANSIT, self::DELIVERED],
            self::DELIVERED->value => [self::COMPLETED],
            self::COMPLETED->value => [],
            self::CANCELLED->value => [],
            self::RETURNED->value => [],
            self::REVIEW_REJECT->value => [],
            self::EXCEPTION->value => [self::PROCESSING, self::CANCELLED],
        ];

        return $transitions[$this->value] ?? [];
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
