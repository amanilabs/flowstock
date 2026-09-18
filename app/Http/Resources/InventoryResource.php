<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reserved = $this->activeReservedQuantity();
        $reorderPoint = $this->product->reorder_point;

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'quantity' => $this->quantity,
            'reserved_quantity' => $reserved,
            'available_quantity' => $this->quantity - $reserved,
            'reorder_point' => $reorderPoint,
            'status' => match (true) {
                $this->quantity <= 0 => 'out_of_stock',
                $this->quantity <= $reorderPoint => 'low_stock',
                default => 'in_stock',
            },
            'updated_at' => $this->updated_at,
        ];
    }
}
