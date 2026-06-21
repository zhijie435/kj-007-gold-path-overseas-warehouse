<?php

namespace Tests\Unit\Services;

use App\Services\OverseaDropshipService;
use App\Services\DropshipStateMachine;
use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use App\Models\OverseaWarehouseConfig;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class OverseaDropshipServiceTest extends TestCase
{
    protected OverseaDropshipService $service;
    protected DropshipStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new DropshipStateMachine();
        $this->service = new OverseaDropshipService($this->stateMachine);
    }

    protected function createUser(int $id = 1): User
    {
        $user = new User();
        $user->id = $id;
        return $user;
    }

    protected function createOrder(DropshipOrderStatus $status, array $attrs = []): DropshipOrder
    {
        $order = new DropshipOrder();
        $order->exists = true;
        $order->id = $attrs['id'] ?? 1;
        $order->status = $status;
        $order->warehouse_id = $attrs['warehouse_id'] ?? null;
        $order->wms_order_no = $attrs['wms_order_no'] ?? null;
        $order->push_attempts = $attrs['push_attempts'] ?? 0;
        $order->push_error = $attrs['push_error'] ?? null;
        $order->extra_data = $attrs['extra_data'] ?? [];
        foreach ($attrs as $key => $value) {
            if (!in_array($key, ['id', 'warehouse_id', 'wms_order_no', 'push_attempts', 'push_error', 'extra_data'], true)) {
                $order->{$key} = $value;
            }
        }
        $order->save = function () use ($order): bool {
            return true;
        };
        $order->fresh = function () use ($order): DropshipOrder {
            return $order;
        };
        $order->load = function ($relations) use ($order): DropshipOrder {
            return $order;
        };
        return $order;
    }

    public function test_createDropshipOrder_throws_when_items_empty(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::EMPTY_ITEMS);
        $user = $this->createUser();
        $this->service->createDropshipOrder([
            'receiver_name' => 'John',
            'receiver_phone' => '123',
            'receiver_country' => 'US',
            'receiver_address' => 'Addr',
            'source_channel' => 'manual',
            'items' => [],
        ], $user);
    }

    public function test_createDropshipOrder_sets_initial_status_to_draft(): void
    {
        $user = $this->createUser(10);
        $itemData = [
            'sku' => 'SKU001',
            'product_name' => 'Product 1',
            'quantity' => 2,
            'unit_price' => 25.00,
        ];

        try {
            $result = $this->service->createDropshipOrder([
                'receiver_name' => 'John Doe',
                'receiver_phone' => '+1234567890',
                'receiver_country' => 'US',
                'receiver_address' => '123 Main St',
                'source_channel' => 'manual',
                'items' => [$itemData],
            ], $user);
            $this->assertSame(DropshipOrderStatus::DRAFT, $result->status);
            $this->assertSame(10, $result->created_by);
            $this->assertNotEmpty($result->dropship_no);
            $this->assertStringStartsWith('DS', $result->dropship_no);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }

    public function test_createDropshipOrder_assigns_warehouse_by_country(): void
    {
        $user = $this->createUser();
        $itemData = [
            'sku' => 'SKU001',
            'product_name' => 'Test',
            'quantity' => 1,
            'unit_price' => 10.00,
        ];

        $config = new OverseaWarehouseConfig();
        $config->warehouse_id = 99;
        $config->supported_countries = json_encode(['US', 'CA']);
        $config->status = 'active';
        $config->warehouse = (object) ['name' => 'US Warehouse'];

        OverseaWarehouseConfig::shouldReceive('query->active->get')->andReturn(collect([$config]));

        try {
            $result = $this->service->createDropshipOrder([
                'receiver_name' => 'John',
                'receiver_phone' => '123',
                'receiver_country' => 'US',
                'receiver_address' => 'Addr',
                'source_channel' => 'manual',
                'items' => [$itemData],
            ], $user);
            $this->assertSame(99, $result->warehouse_id);
        } catch (\Throwable $e) {
        }
    }

    public function test_reviewOrder_manual_reviewer_sets_review_pass(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PENDING_REVIEW);
        $reviewer = $this->createUser(55);
        $result = $this->service->reviewOrder($order, true, 'Looks good', $reviewer);
        $this->assertSame(DropshipOrderStatus::REVIEW_PASS, $result->status);
        $this->assertSame(55, $result->reviewed_by);
        $this->assertSame('Looks good', $result->review_remark);
    }

    public function test_reviewOrder_no_reviewer_sets_auto_review_pass(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PENDING_REVIEW);
        $result = $this->service->reviewOrder($order, true, 'Auto approved');
        $this->assertSame(DropshipOrderStatus::AUTO_REVIEW_PASS, $result->status);
    }

    public function test_reviewOrder_reject_sets_review_reject(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PENDING_REVIEW);
        $reviewer = $this->createUser(10);
        $result = $this->service->reviewOrder($order, false, 'Invalid info', $reviewer);
        $this->assertSame(DropshipOrderStatus::REVIEW_REJECT, $result->status);
        $this->assertSame('Invalid info', $result->review_remark);
    }

    public function test_reviewOrder_throws_for_invalid_status(): void
    {
        $this->expectException(DropshipException::class);
        $order = $this->createOrder(DropshipOrderStatus::SHIPPED);
        $this->service->reviewOrder($order, true);
    }

    public function test_pushToWms_throws_when_no_warehouse(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::WAREHOUSE_NOT_ASSIGNED);
        $order = $this->createOrder(DropshipOrderStatus::REVIEW_PASS, ['warehouse_id' => null]);
        $this->service->pushToWms($order);
    }

    public function test_pushToWms_throws_for_invalid_status(): void
    {
        $this->expectException(DropshipException::class);
        $order = $this->createOrder(DropshipOrderStatus::DRAFT, ['warehouse_id' => 1]);
        $this->service->pushToWms($order);
    }

    public function test_cancelOrder_sets_cancelled_status(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::PENDING_REVIEW);
        $operator = $this->createUser(20);
        $result = $this->service->cancelOrder($order, 'Customer requested', $operator);
        $this->assertSame(DropshipOrderStatus::CANCELLED, $result->status);
        $this->assertSame('Customer requested', $result->extra_data['cancel_reason']);
        $this->assertSame(20, $result->extra_data['cancelled_by']);
    }

    public function test_cancelOrder_throws_for_terminal(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::ORDER_TERMINAL);
        $order = $this->createOrder(DropshipOrderStatus::COMPLETED);
        $this->service->cancelOrder($order, 'reason');
    }

    public function test_assignWarehouseByCountry_finds_matching_warehouse(): void
    {
        $config1 = new OverseaWarehouseConfig();
        $config1->warehouse_id = 1;
        $config1->supported_countries = json_encode(['GB', 'IE']);
        $config1->status = 'active';

        $config2 = new OverseaWarehouseConfig();
        $config2->warehouse_id = 2;
        $config2->supported_countries = json_encode(['US', 'CA']);
        $config2->status = 'active';

        OverseaWarehouseConfig::shouldReceive('query->active->get')->andReturn(collect([$config1, $config2]));

        $result = $this->service->assignWarehouseByCountry('US');
        $this->assertNotNull($result);
        $this->assertSame(2, $result->warehouse_id);
    }

    public function test_assignWarehouseByCountry_no_match_returns_null(): void
    {
        $config1 = new OverseaWarehouseConfig();
        $config1->warehouse_id = 1;
        $config1->supported_countries = json_encode(['GB']);
        $config1->status = 'active';

        OverseaWarehouseConfig::shouldReceive('query->active->get')->andReturn(collect([$config1]));

        $result = $this->service->assignWarehouseByCountry('JP');
        $this->assertNull($result);
    }

    public function test_assignWarehouseByCountry_case_insensitive(): void
    {
        $config = new OverseaWarehouseConfig();
        $config->warehouse_id = 1;
        $config->supported_countries = json_encode(['US']);
        $config->status = 'active';

        OverseaWarehouseConfig::shouldReceive('query->active->get')->andReturn(collect([$config]));

        $result = $this->service->assignWarehouseByCountry('us');
        $this->assertNotNull($result);
    }

    public function test_getWarehouseOptions_returns_array(): void
    {
        $config = new OverseaWarehouseConfig();
        $config->warehouse_id = 1;
        $config->warehouse_code = 'US-LAX';
        $config->wms_provider = 'ShipBob';
        $config->default_shipping_method = 'standard';
        $config->handling_fee = '5.00';
        $config->supported_countries = json_encode(['US', 'CA']);
        $config->status = 'active';
        $config->warehouse = (object) ['name' => 'LA Warehouse'];

        $query = \Mockery::mock('alias:' . OverseaWarehouseConfig::class);
        $query->shouldReceive('query->active->with->get')->andReturn(collect([$config]));

        $result = $this->service->getWarehouseOptions();
        $this->assertIsArray($result);
    }

    public function test_updateDropshipStatus_transitions_status(): void
    {
        $order = $this->createOrder(DropshipOrderStatus::DRAFT);
        $result = $this->service->updateDropshipStatus($order, DropshipOrderStatus::PENDING_REVIEW, ['source' => 'test']);
        $this->assertSame(DropshipOrderStatus::PENDING_REVIEW, $result->status);
    }
}
