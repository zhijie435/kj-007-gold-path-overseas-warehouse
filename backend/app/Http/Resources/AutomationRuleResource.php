<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        $typeEnum = $this->getTypeEnum();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'type_label' => $typeEnum->label(),
            'type_description' => $typeEnum->description(),
            'type_category' => $typeEnum->category(),
            'description' => $this->description,
            'priority' => (int) $this->priority,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse?->id,
                    'name' => $this->warehouse?->name,
                    'code' => $this->warehouse?->code,
                ];
            }),
            'country_code' => $this->country_code,
            'source_channel' => $this->source_channel,
            'min_order_amount' => $this->min_order_amount,
            'max_order_amount' => $this->max_order_amount,
            'active_time_start' => $this->active_time_start,
            'active_time_end' => $this->active_time_end,
            'weekdays' => $this->weekdays,
            'is_enabled' => (bool) $this->is_enabled,
            'stop_chain' => (bool) $this->stop_chain,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                ];
            }),
            'updated_by' => $this->updated_by,
            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater?->id,
                    'name' => $this->updater?->name,
                ];
            }),
            'last_triggered_at' => $this->last_triggered_at?->toDateTimeString(),
            'trigger_count' => (int) $this->trigger_count,
            'success_count' => (int) $this->success_count,
            'failed_count' => (int) $this->failed_count,
            'success_rate' => $this->successRate() . '%',
            'is_within_active_time' => $this->isWithinActiveTime(),
            'is_within_weekday' => $this->isWithinWeekday(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
