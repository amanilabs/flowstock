<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    // 'status' below intentionally keeps the enum instance rather than ->value: identical
    // JSON output (BackedEnum is JsonSerializable), but lets Scramble infer the real
    // OrderStatus type for the OpenAPI docs instead of a bare string.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'refunded_at' => $this->refunded_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
