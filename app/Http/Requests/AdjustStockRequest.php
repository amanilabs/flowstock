<?php

namespace App\Http\Requests;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required', 'integer',
                Rule::exists('warehouses', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', new Enum(StockMovementType::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
