<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dropship_order_id', 'product_id', 'order_item_id',
    'sku', 'product_name', 'specification', 'unit',
    'quantity', 'shipped_quantity', 'unit_price', 'subtotal',
    'unit_cost', 'weight', 'hs_code', 'batch_no',
    'serial_numbers', 'remark',
])]
class DropshipOrderItem extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'shipped_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'weight' => 'decimal:3',
            'serial_numbers' => 'array',
        ];
    }

    public function dropshipOrder(): BelongsTo
    {
        return $this->belongsTo(DropshipOrder::class, 'dropship_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function calculateSubtotal(): float
    {
        return bcmul((string) $this->quantity, (string) $this->unit_price, 2);
    }

    public function calculateTotalWeight(): float
    {
        return bcmul((string) $this->quantity, (string) $this->weight, 3);
    }

    public function calculateTotalCost(): float
    {
        return bcmul((string) $this->quantity, (string) $this->unit_cost, 2);
    }
}
