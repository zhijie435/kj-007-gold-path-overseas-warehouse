<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WmsCallbackLogResource extends JsonResource
{
    public function toArray($request): array
    {
        $typeEnum = $this->getTypeEnum();

        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse?->id,
                    'name' => $this->warehouse?->name,
                    'code' => $this->warehouse?->code,
                ];
            }),
            'wms_provider' => $this->wms_provider,
            'callback_type' => $this->callback_type,
            'callback_type_label' => $typeEnum->label(),
            'callback_type_color' => $typeEnum->color(),
            'wms_order_no' => $this->wms_order_no,
            'dropship_order_id' => $this->dropship_order_id,
            'dropship_order' => $this->whenLoaded('dropshipOrder', function () {
                return $this->dropshipOrder ? [
                    'id' => $this->dropshipOrder->id,
                    'dropship_no' => $this->dropshipOrder->dropship_no,
                    'status' => $this->dropshipOrder->status,
                ] : null;
            }),
            'reference_no' => $this->reference_no,
            'request_id' => $this->request_id,
            'status' => $this->status,
            'is_received' => $this->isReceived(),
            'is_processing' => $this->isProcessing(),
            'is_success' => $this->isSuccess(),
            'is_failed' => $this->isFailed(),
            'is_retry' => $this->isRetry(),
            'request_headers' => $this->request_headers,
            'request_body' => $this->request_body,
            'request_body_array' => $this->getRequestBodyArray(),
            'response_body' => $this->response_body,
            'response_body_array' => $this->getResponseBodyArray(),
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'retry_count' => (int) $this->retry_count,
            'source_ip' => $this->source_ip,
            'processed_by' => $this->processed_by,
            'processor' => $this->whenLoaded('processor', function () {
                return [
                    'id' => $this->processor?->id,
                    'name' => $this->processor?->name,
                ];
            }),
            'extra_data' => $this->extra_data,
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
