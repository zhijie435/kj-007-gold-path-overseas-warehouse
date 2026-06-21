<?php

namespace App\Models;

use App\Enums\DropshipOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'dropship_no', 'order_id', 'external_order_no', 'warehouse_id',
    'supplier_id', 'distributor_id', 'created_by',
    'source_channel', 'fulfillment_type', 'wms_order_no',
    'shipping_method_code', 'tracking_no', 'carrier_name',
    'receiver_name', 'receiver_phone', 'receiver_email',
    'receiver_country', 'receiver_state', 'receiver_city',
    'receiver_postal_code', 'receiver_address',
    'total_items', 'subtotal', 'shipping_fee', 'handling_fee',
    'insurance_fee', 'duty_fee', 'total_cost',
    'declared_value', 'currency', 'weight', 'volume_weight',
    'status', 'reviewed_at', 'reviewed_by', 'review_remark',
    'pushed_at', 'shipped_at', 'delivered_at', 'completed_at',
    'cancelled_at', 'push_attempts', 'push_error',
    'tracking_history', 'customs_info', 'extra_data', 'remark',
])]
class DropshipOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'handling_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'duty_fee' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'declared_value' => 'decimal:2',
            'weight' => 'decimal:3',
            'volume_weight' => 'decimal:3',
            'status' => DropshipOrderStatus::class,
            'reviewed_at' => 'datetime',
            'pushed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'tracking_history' => 'array',
            'customs_info' => 'array',
            'extra_data' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DropshipOrderItem::class, 'dropship_order_id');
    }

    public function callbackLogs(): HasMany
    {
        return $this->hasMany(WmsCallbackLog::class);
    }

    public function scopeByStatus(Builder $query, DropshipOrderStatus|string $status): Builder
    {
        $value = $status instanceof DropshipOrderStatus ? $status->value : $status;
        return $query->where('status', $value);
    }

    public function scopeByWarehouse(Builder $query, $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where('source_channel', $channel);
    }

    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('receiver_country', $country);
    }

    public function scopePendingPush(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DropshipOrderStatus::REVIEW_PASS->value,
            DropshipOrderStatus::AUTO_REVIEW_PASS->value,
            DropshipOrderStatus::PUSH_FAILED->value,
        ]);
    }

    public function scopeInFulfillment(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DropshipOrderStatus::PROCESSING->value,
            DropshipOrderStatus::PUSH_SUCCESS->value,
            DropshipOrderStatus::PICKED->value,
            DropshipOrderStatus::PACKED->value,
        ]);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DropshipOrderStatus::SHIPPED->value,
            DropshipOrderStatus::IN_TRANSIT->value,
            DropshipOrderStatus::CUSTOMS->value,
        ]);
    }

    public function isTerminal(): bool
    {
        return $this->getStatusEnum()->isTerminal();
    }

    public function getStatusEnum(): DropshipOrderStatus
    {
        return DropshipOrderStatus::from($this->getRawOriginal('status') ?? $this->status);
    }

    public function addTrackingEvent(string $status, string $location, string $description): void
    {
        $history = $this->tracking_history ?? [];
        $history[] = [
            'status' => $status,
            'location' => $location,
            'description' => $description,
            'time' => now()->toDateTimeString(),
        ];
        $this->tracking_history = $history;
    }

    public function calculateTotalCost(): float
    {
        return round(
            $this->subtotal + $this->shipping_fee + $this->handling_fee
            + $this->insurance_fee + $this->duty_fee,
            2
        );
    }

    public function generateDropshipNo(): string
    {
        return 'DS' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function getReceiverFullAddress(): string
    {
        $parts = array_filter([
            $this->receiver_address,
            $this->receiver_city,
            $this->receiver_state,
            $this->receiver_postal_code,
            $this->receiver_country,
        ]);
        return implode(', ', $parts);
    }
}
