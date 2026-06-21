<?php

namespace App\Models;

use App\Enums\AutomationRuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'code', 'type', 'description', 'priority',
    'conditions', 'actions', 'warehouse_id', 'country_code',
    'source_channel', 'min_order_amount', 'max_order_amount',
    'active_time_start', 'active_time_end', 'weekdays',
    'is_enabled', 'stop_chain', 'created_by', 'updated_by',
    'last_triggered_at', 'trigger_count', 'success_count', 'failed_count',
])]
class AutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => AutomationRuleType::class,
            'conditions' => 'array',
            'actions' => 'array',
            'weekdays' => 'array',
            'is_enabled' => 'boolean',
            'stop_chain' => 'boolean',
            'priority' => 'integer',
            'min_order_amount' => 'decimal:2',
            'max_order_amount' => 'decimal:2',
            'last_triggered_at' => 'datetime',
            'trigger_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByType(Builder $query, AutomationRuleType|string $type): Builder
    {
        $value = $type instanceof AutomationRuleType ? $type->value : $type;
        return $query->where('type', $value);
    }

    public function scopeByWarehouse(Builder $query, $warehouseId): Builder
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)
                ->orWhereNull('warehouse_id');
        });
    }

    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where(function ($q) use ($countryCode) {
            $q->where('country_code', $countryCode)
                ->orWhereNull('country_code');
        });
    }

    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where(function ($q) use ($channel) {
            $q->where('source_channel', $channel)
                ->orWhereNull('source_channel');
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('id');
    }

    public function getTypeEnum(): AutomationRuleType
    {
        return AutomationRuleType::from($this->getRawOriginal('type') ?? $this->type);
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    public function successRate(): float
    {
        if ($this->trigger_count <= 0) {
            return 0;
        }
        return round(($this->success_count / $this->trigger_count) * 100, 2);
    }

    public function incrementTrigger(bool $success): void
    {
        $this->trigger_count++;
        if ($success) {
            $this->success_count++;
        } else {
            $this->failed_count++;
        }
        $this->last_triggered_at = now();
        $this->save();
    }

    public function isWithinActiveTime(): bool
    {
        if (empty($this->active_time_start) && empty($this->active_time_end)) {
            return true;
        }
        $now = now()->format('H:i:s');
        if (!empty($this->active_time_start) && $now < $this->active_time_start) {
            return false;
        }
        if (!empty($this->active_time_end) && $now > $this->active_time_end) {
            return false;
        }
        return true;
    }

    public function isWithinWeekday(): bool
    {
        if (empty($this->weekdays)) {
            return true;
        }
        $currentDay = now()->dayOfWeekIso;
        return in_array($currentDay, (array) $this->weekdays, true);
    }

    public function isWithinAmountRange(float $amount): bool
    {
        if ($this->min_order_amount !== null && $amount < (float) $this->min_order_amount) {
            return false;
        }
        if ($this->max_order_amount !== null && $amount > (float) $this->max_order_amount) {
            return false;
        }
        return true;
    }

    public function matchesBasicConditions(array $context = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        if (!$this->isWithinActiveTime()) {
            return false;
        }
        if (!$this->isWithinWeekday()) {
            return false;
        }
        if (!empty($context['amount']) && !$this->isWithinAmountRange((float) $context['amount'])) {
            return false;
        }
        return true;
    }
}
