<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\DropshipException;
use PHPUnit\Framework\TestCase;

class DropshipExceptionTest extends TestCase
{
    public function test_invalidStatusTransition_creates_correct_exception(): void
    {
        $e = DropshipException::invalidStatusTransition('draft', 'shipped', ['extra' => 'data']);
        $this->assertSame(DropshipException::INVALID_STATUS_TRANSITION, $e->getErrorCode());
        $this->assertStringContainsString('draft', $e->getMessage());
        $this->assertStringContainsString('shipped', $e->getMessage());
        $this->assertSame(['from' => 'draft', 'to' => 'shipped', 'extra' => 'data'], $e->getContext());
    }

    public function test_orderTerminal_creates_correct_exception(): void
    {
        $e = DropshipException::orderTerminal('已完成', ['action' => 'cancel']);
        $this->assertSame(DropshipException::ORDER_TERMINAL, $e->getErrorCode());
        $this->assertStringContainsString('已完成', $e->getMessage());
        $this->assertSame(['status' => '已完成', 'action' => 'cancel'], $e->getContext());
    }

    public function test_warehouseNotAssigned_creates_correct_exception(): void
    {
        $e = DropshipException::warehouseNotAssigned();
        $this->assertSame(DropshipException::WAREHOUSE_NOT_ASSIGNED, $e->getErrorCode());
        $this->assertStringContainsString('未分配海外仓', $e->getMessage());
    }

    public function test_warehouseConfigInvalid_creates_correct_exception(): void
    {
        $e = DropshipException::warehouseConfigInvalid(['warehouse_id' => 1]);
        $this->assertSame(DropshipException::WAREHOUSE_CONFIG_INVALID, $e->getErrorCode());
        $this->assertStringContainsString('海外仓配置不存在', $e->getMessage());
    }

    public function test_emptyItems_creates_correct_exception(): void
    {
        $e = DropshipException::emptyItems();
        $this->assertSame(DropshipException::EMPTY_ITEMS, $e->getErrorCode());
        $this->assertStringContainsString('商品明细', $e->getMessage());
    }

    public function test_permissionDenied_creates_correct_exception(): void
    {
        $e = DropshipException::permissionDenied('push', ['user_id' => 10]);
        $this->assertSame(DropshipException::PERMISSION_DENIED, $e->getErrorCode());
        $this->assertStringContainsString('push', $e->getMessage());
        $this->assertSame(['action' => 'push', 'user_id' => 10], $e->getContext());
    }

    public function test_wmsApiError_creates_correct_exception(): void
    {
        $e = DropshipException::wmsApiError('ShipBob', 'timeout', ['order_id' => 1]);
        $this->assertSame(DropshipException::WMS_API_ERROR, $e->getErrorCode());
        $this->assertStringContainsString('ShipBob', $e->getMessage());
        $this->assertStringContainsString('timeout', $e->getMessage());
    }

    public function test_orderNotFound_creates_correct_exception(): void
    {
        $e = DropshipException::orderNotFound('DS123456');
        $this->assertSame(DropshipException::ORDER_NOT_FOUND, $e->getErrorCode());
        $this->assertStringContainsString('DS123456', $e->getMessage());
    }

    public function test_withContext_merges_context(): void
    {
        $e = new DropshipException('test', 0, ['a' => 1]);
        $e->withContext(['b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $e->getContext());
    }

    public function test_constructor_sets_properties(): void
    {
        $prev = new \RuntimeException('prev');
        $e = new DropshipException('custom message', 999, ['ctx' => 'val'], $prev);
        $this->assertSame('custom message', $e->getMessage());
        $this->assertSame(999, $e->getCode());
        $this->assertSame(999, $e->getErrorCode());
        $this->assertSame(['ctx' => 'val'], $e->getContext());
        $this->assertSame($prev, $e->getPrevious());
    }
}
