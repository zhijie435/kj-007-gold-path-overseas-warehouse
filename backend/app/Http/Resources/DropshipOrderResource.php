<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DropshipOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusEnum = $this->getStatusEnum();

        return [
            'id' => $this->id,
            'dropship_no' => $this->dropship_no,
            'order_id' => $this->order_id,
            'external_order_no' => $this->external_order_no,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse?->id,
                    'name' => $this->warehouse?->name,
                    'code' => $this->warehouse?->code,
                ];
            }),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier?->id,
                    'name' => $this->supplier?->name,
                ];
            }),
            'distributor_id' => $this->distributor_id,
            'distributor' => $this->whenLoaded('distributor', function () {
                return [
                    'id' => $this->distributor?->id,
                    'name' => $this->distributor?->name,
                ];
            }),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                ];
            }),
            'reviewed_by' => $this->reviewed_by,
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer?->id,
                    'name' => $this->reviewer?->name,
                ];
            }),
            'source_channel' => $this->source_channel,
            'fulfillment_type' => $this->fulfillment_type,
            'wms_order_no' => $this->wms_order_no,
            'shipping_method_code' => $this->shipping_method_code,
            'tracking_no' => $this->tracking_no,
            'carrier_name' => $this->carrier_name,
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'receiver_email' => $this->receiver_email,
            'receiver_country' => $this->receiver_country,
            'receiver_state' => $this->receiver_state,
            'receiver_city' => $this->receiver_city,
            'receiver_postal_code' => $this->receiver_postal_code,
            'receiver_address' => $this->receiver_address,
            'receiver_full_address' => $this->getReceiverFullAddress(),
            'total_items' => (int) $this->total_items,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shipping_fee,
            'handling_fee' => $this->handling_fee,
            'insurance_fee' => $this->insurance_fee,
            'duty_fee' => $this->duty_fee,
            'total_cost' => $this->total_cost,
            'declared_value' => $this->declared_value,
            'currency' => $this->currency,
            'weight' => $this->weight,
            'volume_weight' => $this->volume_weight,
            'status' => $this->status,
            'status_label' => $statusEnum->label(),
            'status_color' => $statusEnum->color(),
            'is_terminal' => $statusEnum->isTerminal(),
            'allowed_transitions' => array_map(function ($status) {
                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color(),
                ];
            }, $statusEnum->allowedTransitions()),
            'review_remark' => $this->review_remark,
            'push_attempts' => (int) $this->push_attempts,
            'push_error' => $this->push_error,
            'tracking_history' => $this->tracking_history,
            'customs_info' => $this->customs_info,
            'extra_data' => $this->extra_data,
            'remark' => $this->remark,
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'pushed_at' => $this->pushed_at?->toDateTimeString(),
            'shipped_at' => $this->shipped_at?->toDateTimeString(),
            'delivered_at' => $this->delivered_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'items' => DropshipOrderItemResource::collection($this->whenLoaded('items')),
            'callback_logs_count' => $this->when($this->relationLoaded('callbackLogs'), function () {
                return $this->callbackLogs?->count() ?? 0;
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
