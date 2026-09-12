<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'slug')->where('tenant_id', $this->user()->tenant_id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
