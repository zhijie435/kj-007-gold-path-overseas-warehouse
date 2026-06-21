<?php

namespace Tests\Unit\Services;

use App\Services\DropshipStateMachine;
use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use PHPUnit\Framework\TestCase;

class DropshipStateMachineTest extends TestCase
{
    protected DropshipStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new DropshipStateMachine();
    }

    protected function createOrder(DropshipOrderStatus $status): DropshipOrder
    {
        $order = new DropshipOrder();
        $order->status = $status;
        $order->exists = true;
        $order->save = function () use ($order): bool {
            return true;
        };
        return $order;
    }

    public function test_canReview_returns_true_for_draft_and_pending(): void
    {
        $this->assertTrue($this->stateMachine->canReview($this->createOrder(DropshipOrderStatus::DRAFT)));
        $this->assertTrue($this->stateMachine->canReview($this->createOrder(DropshipOrderStatus::PENDING_REVIEW)));
    }

    public function test_canReview_returns_false_for_other_statuses(): void
    {
        $nonReviewable = [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::REVIEW_REJECT,
            DropshipOrderStatus::PUSHING,
            DropshipOrderStatus::PUSH_SUCCESS,
            DropshipOrderStatus::SHIPPED,
            DropshipOrderStatus::COMPLETED,
            DropshipOrderStatus::CANCELLED,
        ];
        foreach ($nonReviewable as $status) {
            $this->assertFalse(
                $this->stateMachine->canReview($this->createOrder($status)),
                "Status {$status->value} should not be reviewable"
            );
        }
    }

    public function test_canPushToWms_returns_true_for_passed_and_failed(): void
    {
        $this->assertTrue($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::REVIEW_PASS)));
        $this->assertTrue($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::AUTO_REVIEW_PASS)));
        $this->assertTrue($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::PUSH_FAILED)));
    }

    public function test_canPushToWms_returns_false_for_other_statuses(): void
    {
        $this->assertFalse($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::DRAFT)));
        $this->assertFalse($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::PENDING_REVIEW)));
        $this->assertFalse($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::SHIPPED)));
        $this->assertFalse($this->stateMachine->canPushToWms($this->createOrder(DropshipOrderStatus::COMPLETED)));
    }

    public function test_canCancel_returns_false_for_terminal(): void
    {
        $terminal = [
            DropshipOrderStatus::COMPLETED,
            DropshipOrderStatus::CANCELLED,
            DropshipOrderStatus::RETURNED,
            DropshipOrderStatus::REVIEW_REJECT,
        ];
        foreach ($terminal as $status) {
            $this->assertFalse(
                $this->stateMachine->canCancel($this->createOrder($status)),
                "Status {$status->value} should not be cancellable (terminal)"
            );
        }
    }

    public function test_canCancel_returns_true_for_non_terminal(): void
    {
        $nonTerminal = [
            DropshipOrderStatus::DRAFT,
            DropshipOrderStatus::PENDING_REVIEW,
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::PUSHING,
            DropshipOrderStatus::PUSH_FAILED,
            DropshipOrderStatus::PROCESSING,
            DropshipOrderStatus::EXCEPTION,
        ];
        foreach ($nonTerminal as $status) {
            $this->assertTrue(
                $this->stateMachine->canCancel($this->createOrder($status)),
                "Status {$status->value} should be cancellable"
            );
        }
    }

    public function test_canEdit_returns_false_for_shipping_statuses(): void
    {
        $notEditable = [
            DropshipOrderStatus::PUSHING,
            DropshipOrderStatus::PROCESSING,
            DropshipOrderStatus::PICKED,
            DropshipOrderStatus::PACKED,
            DropshipOrderStatus::SHIPPED,
            DropshipOrderStatus::IN_TRANSIT,
            DropshipOrderStatus::CUSTOMS,
            DropshipOrderStatus::COMPLETED,
            DropshipOrderStatus::CANCELLED,
            DropshipOrderStatus::RETURNED,
            DropshipOrderStatus::REVIEW_REJECT,
        ];
        foreach ($notEditable as $status) {
            $this->assertFalse(
                $this->stateMachine->canEdit($this->createOrder($status)),
                "Status {$status->value} should not be editable"
            );
        }
    }

    public function test_canEdit_returns_true_for_draft_and_review(): void
    {
        $editable = [
            DropshipOrderStatus::DRAFT,
            DropshipOrderStatus::PENDING_REVIEW,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
            DropshipOrderStatus::PUSH_SUCCESS,
            DropshipOrderStatus::EXCEPTION,
        ];
        foreach ($editable as $status) {
            $this->assertTrue(
                $this->stateMachine->canEdit($this->createOrder($status)),
                "Status {$status->value} should be editable"
            );
        }
    }

    public function test_canDelete_only_terminal(): void
    {
        $this->assertTrue($this->stateMachine->canDelete($this->createOrder(DropshipOrderStatus::COMPLETED)));
        $this->assertTrue($this->stateMachine->canDelete($this->createOrder(DropshipOrderStatus::CANCELLED)));
        $this->assertFalse($this->stateMachine->canDelete($this->createOrder(DropshipOrderStatus::DRAFT)));
        $this->assertFalse($this->stateMachine->canDelete($this->createOrder(DropshipOrderStatus::SHIPPED)));
    }

    public function test_canRetryPush_only_push_failed(): void
    {
        $this->assertTrue($this->stateMachine->canRetryPush($this->createOrder(DropshipOrderStatus::PUSH_FAILED)));
        $this->assertFalse($this->stateMachine->canRetryPush($this->createOrder(DropshipOrderStatus::DRAFT)));
        $this->assertFalse($this->stateMachine->canRetryPush($this->createOrder(DropshipOrderStatus::REVIEW_PASS)));
        $this->assertFalse($this->stateMachine->canRetryPush($this->createOrder(DropshipOrderStatus::EXCEPTION)));
    }

    public function test_canSyncTracking_requires_warehouse_and_wms_no(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PUSH_SUCCESS);
        $this->assertFalse($this->stateMachine->canSyncTracking($order));

        $order->warehouse_id = 1;
        $this->assertFalse($this->stateMachine->canSyncTracking($order));

        $order->wms_order_no = 'WMS123';
        $this->assertTrue($this->stateMachine->canSyncTracking($order));
    }

    public function test_ensureCanReview_throws_for_invalid_status(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::INVALID_STATUS_TRANSITION);
        $this->stateMachine->ensureCanReview($this->createOrder(DropshipOrderStatus::SHIPPED));
    }

    public function test_ensureCanReview_passes_for_valid_status(): void
    {
        $this->stateMachine->ensureCanReview($this->createOrder(DropshipOrderStatus::DRAFT));
        $this->addToAssertionCount(1);
    }

    public function test_ensureCanPushToWms_throws_for_invalid_status(): void
    {
        $this->expectException(DropshipException::class);
        $this->stateMachine->ensureCanPushToWms($this->createOrder(DropshipOrderStatus::DRAFT));
    }

    public function test_ensureCanCancel_throws_for_terminal(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::ORDER_TERMINAL);
        $this->stateMachine->ensureCanCancel($this->createOrder(DropshipOrderStatus::COMPLETED));
    }

    public function test_ensureCanEdit_throws_for_invalid(): void
    {
        $this->expectException(DropshipException::class);
        $this->stateMachine->ensureCanEdit($this->createOrder(DropshipOrderStatus::SHIPPED));
    }

    public function test_ensureCanDelete_throws_for_non_terminal(): void
    {
        $this->expectException(DropshipException::class);
        $this->stateMachine->ensureCanDelete($this->createOrder(DropshipOrderStatus::DRAFT));
    }

    public function test_ensureCanRetryPush_throws_for_wrong_status(): void
    {
        $this->expectException(DropshipException::class);
        $this->stateMachine->ensureCanRetryPush($this->createOrder(DropshipOrderStatus::DRAFT));
    }

    public function test_ensureCanTransition_throws_for_invalid_transition(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::INVALID_STATUS_TRANSITION);
        $this->stateMachine->ensureCanTransition(
            $this->createOrder(DropshipOrderStatus::DRAFT),
            DropshipOrderStatus::SHIPPED
        );
    }

    public function test_ensureCanTransition_passes_for_valid(): void
    {
        $this->stateMachine->ensureCanTransition(
            $this->createOrder(DropshipOrderStatus::DRAFT),
            DropshipOrderStatus::PENDING_REVIEW
        );
        $this->addToAssertionCount(1);
    }

    public function test_transition_updates_status_and_timestamp(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::DRAFT);

        $result = $this->stateMachine->transition($order, DropshipOrderStatus::PENDING_REVIEW, ['source' => 'test']);

        $this->assertSame(DropshipOrderStatus::PENDING_REVIEW, $result->status);
        $this->assertNotNull($result->extra_data['status_context']);
        $this->assertSame('draft', $result->extra_data['status_context']['from']);
        $this->assertSame('pending_review', $result->extra_data['status_context']['to']);
        $this->assertSame('test', $result->extra_data['status_context']['context']['source']);
    }

    public function test_transition_sets_reviewed_at_timestamp(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PENDING_REVIEW);
        $result = $this->stateMachine->transition($order, DropshipOrderStatus::REVIEW_PASS);
        $this->assertNotNull($result->reviewed_at);
    }

    public function test_getPushableStatuses_returns_expected(): void
    {
        $statuses = $this->stateMachine->getPushableStatuses();
        $this->assertContains(DropshipOrderStatus::REVIEW_PASS, $statuses);
        $this->assertContains(DropshipOrderStatus::AUTO_REVIEW_PASS, $statuses);
        $this->assertContains(DropshipOrderStatus::PUSH_FAILED, $statuses);
        $this->assertCount(3, $statuses);
    }

    public function test_getReviewableStatuses_returns_expected(): void
    {
        $statuses = $this->stateMachine->getReviewableStatuses();
        $this->assertContains(DropshipOrderStatus::DRAFT, $statuses);
        $this->assertContains(DropshipOrderStatus::PENDING_REVIEW, $statuses);
        $this->assertCount(2, $statuses);
    }

    public function test_mapWmsStatus_maps_correctly(): void
    {
        $this->assertSame(DropshipOrderStatus::PROCESSING, $this->stateMachine->mapWmsStatus('processing'));
        $this->assertSame(DropshipOrderStatus::PICKED, $this->stateMachine->mapWmsStatus('picked'));
        $this->assertSame(DropshipOrderStatus::PACKED, $this->stateMachine->mapWmsStatus('packed'));
        $this->assertSame(DropshipOrderStatus::SHIPPED, $this->stateMachine->mapWmsStatus('shipped'));
        $this->assertSame(DropshipOrderStatus::IN_TRANSIT, $this->stateMachine->mapWmsStatus('in_transit'));
        $this->assertSame(DropshipOrderStatus::IN_TRANSIT, $this->stateMachine->mapWmsStatus('transit'));
        $this->assertSame(DropshipOrderStatus::CUSTOMS, $this->stateMachine->mapWmsStatus('customs'));
        $this->assertSame(DropshipOrderStatus::CUSTOMS, $this->stateMachine->mapWmsStatus('clearance'));
        $this->assertSame(DropshipOrderStatus::DELIVERED, $this->stateMachine->mapWmsStatus('delivered'));
        $this->assertSame(DropshipOrderStatus::COMPLETED, $this->stateMachine->mapWmsStatus('completed'));
        $this->assertSame(DropshipOrderStatus::CANCELLED, $this->stateMachine->mapWmsStatus('cancelled'));
        $this->assertSame(DropshipOrderStatus::RETURNED, $this->stateMachine->mapWmsStatus('returned'));
        $this->assertSame(DropshipOrderStatus::EXCEPTION, $this->stateMachine->mapWmsStatus('exception'));
    }

    public function test_mapWmsStatus_case_insensitive(): void
    {
        $this->assertSame(DropshipOrderStatus::SHIPPED, $this->stateMachine->mapWmsStatus('SHIPPED'));
        $this->assertSame(DropshipOrderStatus::SHIPPED, $this->stateMachine->mapWmsStatus('Shipped'));
    }

    public function test_mapWmsStatus_unknown_returns_null(): void
    {
        $this->assertNull($this->stateMachine->mapWmsStatus('unknown_status'));
    }
}
