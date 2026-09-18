<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_address_line1' => $this->billing_address_line1,
            'billing_address_line2' => $this->billing_address_line2,
            'billing_city' => $this->billing_city,
            'billing_state' => $this->billing_state,
            'billing_postal_code' => $this->billing_postal_code,
            'billing_country' => $this->billing_country,
            'shipping_address_line1' => $this->shipping_address_line1,
            'shipping_address_line2' => $this->shipping_address_line2,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_country' => $this->shipping_country,
            'notes' => $this->notes,
            // Only present when the query eager-loads them via withCount/withSum
            // (the index listing) — null on show(), which doesn't need them.
            'order_count' => $this->order_count ?? null,
            'total_spent' => $this->total_spent !== null ? (float) $this->total_spent : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
