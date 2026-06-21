<?php

namespace Tests\Unit\Models;

use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use App\Enums\DropshipOrderStatus;
use PHPUnit\Framework\TestCase;

class DropshipOrderTest extends TestCase
{
    protected function createOrder(array $attrs = []): DropshipOrder
    {
        $order = new DropshipOrder();
        foreach ($attrs as $key => $value) {
            $order->{$key} = $value;
        }
        return $order;
    }

    public function test_calculateTotalCost_sums_fees(): void
    {
        $order = $this->createOrder([
            'subtotal' => 100.00,
            'shipping_fee' => 12.50,
            'handling_fee' => 5.00,
            'insurance_fee' => 2.00,
            'duty_fee' => 8.50,
        ]);
        $this->assertSame(128.00, $order->calculateTotalCost());
    }

    public function test_calculateTotalCost_with_zero_fees(): void
    {
        $order = $this->createOrder(['subtotal' => 50.00]);
        $this->assertSame(50.00, $order->calculateTotalCost());
    }

    public function test_generateDropshipNo_format(): void
    {
        $order = $this->createOrder();
        $no = $order->generateDropshipNo();
        $this->assertStringStartsWith('DS', $no);
        $this->assertSame(18, strlen($no));
    }

    public function test_generateDropshipNo_unique(): void
    {
        $order = $this->createOrder();
        $no1 = $order->generateDropshipNo();
        usleep(1000);
        $no2 = $order->generateDropshipNo();
        $this->assertNotSame($no1, $no2);
    }

    public function test_getReceiverFullAddress_concatenates_parts(): void
    {
        $order = $this->createOrder([
            'receiver_address' => '123 Main St',
            'receiver_city' => 'Los Angeles',
            'receiver_state' => 'CA',
            'receiver_postal_code' => '90001',
            'receiver_country' => 'US',
        ]);
        $addr = $order->getReceiverFullAddress();
        $this->assertStringContainsString('123 Main St', $addr);
        $this->assertStringContainsString('Los Angeles', $addr);
        $this->assertStringContainsString('CA', $addr);
        $this->assertStringContainsString('90001', $addr);
        $this->assertStringContainsString('US', $addr);
    }

    public function test_getReceiverFullAddress_filters_empty(): void
    {
        $order = $this->createOrder([
            'receiver_address' => '123 Main St',
            'receiver_country' => 'US',
        ]);
        $addr = $order->getReceiverFullAddress();
        $this->assertSame('123 Main St, US', $addr);
    }

    public function test_addTrackingEvent_appends_to_history(): void
    {
        $order = $this->createOrder();
        $order->addTrackingEvent('shipped', 'New York', 'Package shipped');
        $history = $order->tracking_history;
        $this->assertCount(1, $history);
        $this->assertSame('shipped', $history[0]['status']);
        $this->assertSame('New York', $history[0]['location']);
        $this->assertSame('Package shipped', $history[0]['description']);
        $this->assertArrayHasKey('time', $history[0]);
    }

    public function test_addTrackingEvent_multiple_events(): void
    {
        $order = $this->createOrder();
        $order->addTrackingEvent('picked', 'Warehouse', 'Picked up');
        $order->addTrackingEvent('shipped', 'NY', 'Shipped out');
        $this->assertCount(2, $order->tracking_history);
    }

    public function test_isTerminal_delegates_to_enum(): void
    {
        $completed = $this->createOrder();
        $completed->status = DropshipOrderStatus::COMPLETED;
        $this->assertTrue($completed->isTerminal());

        $draft = $this->createOrder();
        $draft->status = DropshipOrderStatus::DRAFT;
        $this->assertFalse($draft->isTerminal());
    }
}
