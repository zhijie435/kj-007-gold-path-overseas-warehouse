<?php

namespace Tests\Unit\Enums;

use App\Enums\DropshipOrderStatus;
use PHPUnit\Framework\TestCase;

class DropshipOrderStatusTest extends TestCase
{
    public function test_all_enum_cases_exist(): void
    {
        $expected = [
            'draft', 'pending_review', 'auto_review_pass', 'review_pass', 'review_reject',
            'pushing', 'push_success', 'push_failed', 'processing', 'picked', 'packed',
            'shipped', 'in_transit', 'customs', 'delivered', 'completed',
            'cancelled', 'returned', 'exception',
        ];
        $actual = array_map(fn ($c) => $c->value, DropshipOrderStatus::cases());
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_label_returns_chinese_label(): void
    {
        $this->assertSame('草稿', DropshipOrderStatus::DRAFT->label());
        $this->assertSame('待审核', DropshipOrderStatus::PENDING_REVIEW->label());
        $this->assertSame('审核通过', DropshipOrderStatus::REVIEW_PASS->label());
        $this->assertSame('推单中', DropshipOrderStatus::PUSHING->label());
        $this->assertSame('推单成功', DropshipOrderStatus::PUSH_SUCCESS->label());
        $this->assertSame('推单失败', DropshipOrderStatus::PUSH_FAILED->label());
        $this->assertSame('已发货', DropshipOrderStatus::SHIPPED->label());
        $this->assertSame('运输中', DropshipOrderStatus::IN_TRANSIT->label());
        $this->assertSame('已完成', DropshipOrderStatus::COMPLETED->label());
        $this->assertSame('已取消', DropshipOrderStatus::CANCELLED->label());
        $this->assertSame('异常', DropshipOrderStatus::EXCEPTION->label());
    }

    public function test_color_returns_expected_color(): void
    {
        $this->assertSame('info', DropshipOrderStatus::DRAFT->color());
        $this->assertSame('warning', DropshipOrderStatus::PENDING_REVIEW->color());
        $this->assertSame('success', DropshipOrderStatus::REVIEW_PASS->color());
        $this->assertSame('danger', DropshipOrderStatus::REVIEW_REJECT->color());
        $this->assertSame('success', DropshipOrderStatus::SHIPPED->color());
        $this->assertSame('danger', DropshipOrderStatus::EXCEPTION->color());
    }

    public function test_isTerminal_identifies_terminal_statuses(): void
    {
        $this->assertTrue(DropshipOrderStatus::COMPLETED->isTerminal());
        $this->assertTrue(DropshipOrderStatus::CANCELLED->isTerminal());
        $this->assertTrue(DropshipOrderStatus::RETURNED->isTerminal());
        $this->assertTrue(DropshipOrderStatus::REVIEW_REJECT->isTerminal());

        $nonTerminal = [
            DropshipOrderStatus::DRAFT,
            DropshipOrderStatus::PENDING_REVIEW,
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::PUSHING,
            DropshipOrderStatus::PUSH_SUCCESS,
            DropshipOrderStatus::PROCESSING,
            DropshipOrderStatus::SHIPPED,
            DropshipOrderStatus::IN_TRANSIT,
            DropshipOrderStatus::EXCEPTION,
        ];
        foreach ($nonTerminal as $status) {
            $this->assertFalse($status->isTerminal(), "Status {$status->value} should not be terminal");
        }
    }

    public function test_canTransitionTo_valid_transitions(): void
    {
        $this->assertTrue(DropshipOrderStatus::DRAFT->canTransitionTo(DropshipOrderStatus::PENDING_REVIEW));
        $this->assertTrue(DropshipOrderStatus::DRAFT->canTransitionTo(DropshipOrderStatus::CANCELLED));
        $this->assertTrue(DropshipOrderStatus::PENDING_REVIEW->canTransitionTo(DropshipOrderStatus::REVIEW_PASS));
        $this->assertTrue(DropshipOrderStatus::PENDING_REVIEW->canTransitionTo(DropshipOrderStatus::AUTO_REVIEW_PASS));
        $this->assertTrue(DropshipOrderStatus::PENDING_REVIEW->canTransitionTo(DropshipOrderStatus::REVIEW_REJECT));
        $this->assertTrue(DropshipOrderStatus::REVIEW_PASS->canTransitionTo(DropshipOrderStatus::PUSHING));
        $this->assertTrue(DropshipOrderStatus::PUSHING->canTransitionTo(DropshipOrderStatus::PUSH_SUCCESS));
        $this->assertTrue(DropshipOrderStatus::PUSHING->canTransitionTo(DropshipOrderStatus::PUSH_FAILED));
        $this->assertTrue(DropshipOrderStatus::PUSH_FAILED->canTransitionTo(DropshipOrderStatus::PUSHING));
        $this->assertTrue(DropshipOrderStatus::PUSH_SUCCESS->canTransitionTo(DropshipOrderStatus::PROCESSING));
        $this->assertTrue(DropshipOrderStatus::PROCESSING->canTransitionTo(DropshipOrderStatus::PICKED));
        $this->assertTrue(DropshipOrderStatus::PICKED->canTransitionTo(DropshipOrderStatus::PACKED));
        $this->assertTrue(DropshipOrderStatus::PACKED->canTransitionTo(DropshipOrderStatus::SHIPPED));
        $this->assertTrue(DropshipOrderStatus::SHIPPED->canTransitionTo(DropshipOrderStatus::IN_TRANSIT));
        $this->assertTrue(DropshipOrderStatus::IN_TRANSIT->canTransitionTo(DropshipOrderStatus::DELIVERED));
        $this->assertTrue(DropshipOrderStatus::DELIVERED->canTransitionTo(DropshipOrderStatus::COMPLETED));
        $this->assertTrue(DropshipOrderStatus::EXCEPTION->canTransitionTo(DropshipOrderStatus::PROCESSING));
    }

    public function test_canTransitionTo_invalid_transitions(): void
    {
        $this->assertFalse(DropshipOrderStatus::DRAFT->canTransitionTo(DropshipOrderStatus::SHIPPED));
        $this->assertFalse(DropshipOrderStatus::COMPLETED->canTransitionTo(DropshipOrderStatus::DRAFT));
        $this->assertFalse(DropshipOrderStatus::CANCELLED->canTransitionTo(DropshipOrderStatus::PENDING_REVIEW));
        $this->assertFalse(DropshipOrderStatus::SHIPPED->canTransitionTo(DropshipOrderStatus::DRAFT));
        $this->assertFalse(DropshipOrderStatus::REVIEW_REJECT->canTransitionTo(DropshipOrderStatus::PENDING_REVIEW));
        $this->assertFalse(DropshipOrderStatus::PUSH_SUCCESS->canTransitionTo(DropshipOrderStatus::DRAFT));
    }

    public function test_allowedTransitions_returns_array(): void
    {
        $draftTransitions = DropshipOrderStatus::DRAFT->allowedTransitions();
        $this->assertIsArray($draftTransitions);
        $this->assertContains(DropshipOrderStatus::PENDING_REVIEW, $draftTransitions);
        $this->assertContains(DropshipOrderStatus::CANCELLED, $draftTransitions);

        $completedTransitions = DropshipOrderStatus::COMPLETED->allowedTransitions();
        $this->assertEmpty($completedTransitions);
    }

    public function test_timestampField_returns_correct_field(): void
    {
        $this->assertSame('reviewed_at', DropshipOrderStatus::REVIEW_PASS->timestampField());
        $this->assertSame('reviewed_at', DropshipOrderStatus::AUTO_REVIEW_PASS->timestampField());
        $this->assertSame('reviewed_at', DropshipOrderStatus::REVIEW_REJECT->timestampField());
        $this->assertSame('pushed_at', DropshipOrderStatus::PUSH_SUCCESS->timestampField());
        $this->assertSame('shipped_at', DropshipOrderStatus::SHIPPED->timestampField());
        $this->assertSame('delivered_at', DropshipOrderStatus::DELIVERED->timestampField());
        $this->assertSame('completed_at', DropshipOrderStatus::COMPLETED->timestampField());
        $this->assertSame('cancelled_at', DropshipOrderStatus::CANCELLED->timestampField());
        $this->assertNull(DropshipOrderStatus::DRAFT->timestampField());
        $this->assertNull(DropshipOrderStatus::PROCESSING->timestampField());
    }

    public function test_options_returns_all_options(): void
    {
        $options = DropshipOrderStatus::options();
        $this->assertCount(count(DropshipOrderStatus::cases()), $options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('color', $option);
        }
    }
}
