<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The inventory listing eager-loads this as `active_reserved_quantity`
        // (a correlated subquery) to avoid one activeReservedQuantity() query
        // per row — fall back to the live per-row query for any other caller.
        $reserved = $this->active_reserved_quantity !== null
            ? (int) $this->active_reserved_quantity
            : $this->activeReservedQuantity();
        $reorderPoint = $this->reorder_point;

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
