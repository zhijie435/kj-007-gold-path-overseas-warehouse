<?php

namespace Tests\Unit\Models;

use App\Models\DropshipOrderItem;
use PHPUnit\Framework\TestCase;

class DropshipOrderItemTest extends TestCase
{
    protected function createItem(array $attrs = []): DropshipOrderItem
    {
        $item = new DropshipOrderItem();
        foreach ($attrs as $key => $value) {
            $item->{$key} = $value;
        }
        return $item;
    }

    public function test_calculateSubtotal_multiplies_quantity_price(): void
    {
        $item = $this->createItem(['quantity' => 3, 'unit_price' => 10.50]);
        $this->assertSame('31.50', $item->calculateSubtotal());
    }

    public function test_calculateSubtotal_zero_quantity(): void
    {
        $item = $this->createItem(['quantity' => 0, 'unit_price' => 100.00]);
        $this->assertSame('0.00', $item->calculateSubtotal());
    }

    public function test_calculateSubtotal_decimal_precision(): void
    {
        $item = $this->createItem(['quantity' => 2, 'unit_price' => 9.99]);
        $this->assertSame('19.98', $item->calculateSubtotal());
    }

    public function test_calculateTotalWeight_multiplies(): void
    {
        $item = $this->createItem(['quantity' => 5, 'weight' => 0.250]);
        $this->assertSame('1.250', $item->calculateTotalWeight());
    }

    public function test_calculateTotalCost_multiplies(): void
    {
        $item = $this->createItem(['quantity' => 4, 'unit_cost' => 7.50]);
        $this->assertSame('30.00', $item->calculateTotalCost());
    }
}
