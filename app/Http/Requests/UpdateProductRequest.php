<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'sku' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->ignore($this->route('product')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit_of_measure' => ['sometimes', 'required', 'string', 'max:50'],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
