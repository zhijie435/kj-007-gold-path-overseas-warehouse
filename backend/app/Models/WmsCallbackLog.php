<?php

namespace App\Models;

use App\Enums\WmsCallbackType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warehouse_id', 'wms_provider', 'callback_type', 'wms_order_no',
    'dropship_order_id', 'reference_no', 'request_id', 'status',
    'request_headers', 'request_body', 'response_body',
    'error_code', 'error_message', 'retry_count',
    'processed_at', 'source_ip', 'processed_by', 'extra_data',
])]
class WmsCallbackLog extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'callback_type' => WmsCallbackType::class,
            'retry_count' => 'integer',
            'processed_at' => 'datetime',
            'extra_data' => 'array',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function dropshipOrder(): BelongsTo
    {
        return $this->belongsTo(DropshipOrder::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeByType(Builder $query, WmsCallbackType|string $type): Builder
    {
        $value = $type instanceof WmsCallbackType ? $type->value : $type;
        return $query->where('callback_type', $value);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByWarehouse(Builder $query, $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('wms_provider', $provider);
    }

    public function scopeByOrder(Builder $query, $dropshipOrderId): Builder
    {
        return $query->where('dropship_order_id', $dropshipOrderId);
    }

    public function scopeByRequestId(Builder $query, string $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['received', 'retry']);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function getTypeEnum(): WmsCallbackType
    {
        return WmsCallbackType::from($this->getRawOriginal('callback_type') ?? $this->callback_type);
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRetry(): bool
    {
        return $this->status === 'retry';
    }

    public function markProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markSuccess(string $responseBody = null): void
    {
        $this->status = 'success';
        $this->processed_at = now();
        if ($responseBody !== null) {
            $this->response_body = $responseBody;
        }
        $this->save();
    }

    public function markFailed(string $errorCode = null, string $errorMessage = null, int $maxRetry = 3): void
    {
        $this->retry_count++;
        if ($this->retry_count >= $maxRetry) {
            $this->status = 'failed';
        } else {
            $this->status = 'retry';
        }
        $this->error_code = $errorCode;
        $this->error_message = $errorMessage;
        $this->save();
    }

    public function getRequestBodyArray(): array
    {
        $decoded = json_decode($this->request_body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getResponseBodyArray(): array
    {
        if (empty($this->response_body)) {
            return [];
        }
        $decoded = json_decode($this->response_body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
