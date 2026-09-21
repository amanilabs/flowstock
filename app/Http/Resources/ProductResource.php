<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'unit_of_measure' => $this->unit_of_measure,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'margin' => $this->margin,
            'margin_percentage' => $this->marginPercentage,
            // Only present when the query eager-loads it via withSum('stock', 'quantity')
            // (the index listing) — null on show(), which doesn't need it.
            'total_stock' => $this->stock_sum_quantity !== null ? (int) $this->stock_sum_quantity : null,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
