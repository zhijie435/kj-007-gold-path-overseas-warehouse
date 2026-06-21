<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OverseaWarehouseConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse?->id,
                    'name' => $this->warehouse?->name,
                    'code' => $this->warehouse?->code,
                    'country' => $this->warehouse?->country,
                    'city' => $this->warehouse?->city,
                ];
            }),
            'wms_provider' => $this->wms_provider,
            'api_endpoint' => $this->api_endpoint,
            'has_api_key' => !empty($this->api_key),
            'has_api_secret' => !empty($this->api_secret),
            'warehouse_code' => $this->warehouse_code,
            'default_shipping_method' => $this->default_shipping_method,
            'handling_fee' => $this->handling_fee,
            'storage_fee_per_cbm' => $this->storage_fee_per_cbm,
            'sla_processing_hours' => $this->sla_processing_hours,
            'auto_push_enabled' => (bool) $this->auto_push_enabled,
            'auto_sync_inventory' => (bool) $this->auto_sync_inventory,
            'inventory_sync_interval_min' => (int) $this->inventory_sync_interval_min,
            'auto_sync_tracking' => (bool) $this->auto_sync_tracking,
            'tracking_sync_interval_min' => (int) $this->tracking_sync_interval_min,
            'supported_countries' => $this->getSupportedCountriesArray(),
            'extra_config' => $this->extra_config,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_testing' => $this->isTesting(),
            'is_error' => $this->isError(),
            'last_inventory_sync_at' => $this->last_inventory_sync_at?->toDateTimeString(),
            'last_tracking_sync_at' => $this->last_tracking_sync_at?->toDateTimeString(),
            'last_api_call_at' => $this->last_api_call_at?->toDateTimeString(),
            'callback_logs_count' => $this->when($this->relationLoaded('callbackLogs'), function () {
                return $this->callbackLogs?->count() ?? 0;
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
