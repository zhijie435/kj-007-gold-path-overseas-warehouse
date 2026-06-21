<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DropshipOrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'dropship_order_id' => $this->dropship_order_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product?->id,
                    'name' => $this->product?->name,
                    'image' => $this->product?->image,
                ];
            }),
            'order_item_id' => $this->order_item_id,
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'specification' => $this->specification,
            'unit' => $this->unit,
            'quantity' => (int) $this->quantity,
            'shipped_quantity' => (int) ($this->shipped_quantity ?? 0),
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'unit_cost' => $this->unit_cost,
            'weight' => $this->weight,
            'total_weight' => $this->calculateTotalWeight(),
            'total_cost' => $this->calculateTotalCost(),
            'hs_code' => $this->hs_code,
            'batch_no' => $this->batch_no,
            'serial_numbers' => $this->serial_numbers,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
