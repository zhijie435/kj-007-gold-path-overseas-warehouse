<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'warehouse_id', 'wms_provider', 'api_endpoint', 'api_key', 'api_secret',
    'warehouse_code', 'default_shipping_method',
    'handling_fee', 'storage_fee_per_cbm', 'sla_processing_hours',
    'auto_push_enabled', 'auto_sync_inventory', 'inventory_sync_interval_min',
    'auto_sync_tracking', 'tracking_sync_interval_min',
    'supported_countries', 'extra_config', 'status',
    'last_inventory_sync_at', 'last_tracking_sync_at', 'last_api_call_at',
])]
class OverseaWarehouseConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'oversea_warehouse_configs';

    protected function casts(): array
    {
        return [
            'handling_fee' => 'decimal:2',
            'storage_fee_per_cbm' => 'decimal:2',
            'auto_push_enabled' => 'boolean',
            'auto_sync_inventory' => 'boolean',
            'auto_sync_tracking' => 'boolean',
            'extra_config' => 'array',
            'last_inventory_sync_at' => 'datetime',
            'last_tracking_sync_at' => 'datetime',
            'last_api_call_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function callbackLogs(): HasMany
    {
        return $this->hasMany(WmsCallbackLog::class, 'warehouse_id', 'warehouse_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('wms_provider', $provider);
    }

    public function scopeAutoPushEnabled(Builder $query): Builder
    {
        return $query->where('auto_push_enabled', true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTesting(): bool
    {
        return $this->status === 'testing';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function getSupportedCountriesArray(): array
    {
        if (empty($this->supported_countries)) {
            return [];
        }
        $decoded = json_decode($this->supported_countries, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function supportsCountry(string $countryCode): bool
    {
        $countries = $this->getSupportedCountriesArray();
        if (empty($countries)) {
            return true;
        }
        return in_array(strtoupper($countryCode), array_map('strtoupper', $countries), true);
    }
}
