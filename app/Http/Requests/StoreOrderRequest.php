<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    // order_items.subtotal and orders.total_amount are both decimal(12,4) —
    // the largest value either column can hold.
    private const string MAX_DECIMAL_12_4 = '99999999.9999';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'warehouse_id' => [
                'required', 'integer',
                Rule::exists('warehouses', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            // 1,000,000 is a generous ceiling on any single line's quantity; the
            // withValidator check below catches the real constraint (the
            // resulting subtotal/total overflowing their decimal(12,4) columns).
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }

            $total = '0';

            foreach ($items as $index => $item) {
                $quantity = $item['quantity'] ?? null;
                if (! is_numeric($quantity)) {
                    continue;
                }

                $unitPrice = $item['unit_price'] ?? null;
                if ($unitPrice === null) {
                    $unitPrice = Product::find($item['product_id'] ?? null)?->selling_price;
                }
                if ($unitPrice === null || ! is_numeric($unitPrice)) {
                    continue;
                }

                $subtotal = bcmul((string) $unitPrice, (string) $quantity, 4);

                if (bccomp($subtotal, self::MAX_DECIMAL_12_4, 4) > 0) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'This quantity produces a line total that is too large.'
                    );

                    continue;
                }

                $total = bcadd($total, $subtotal, 4);
            }

            if (bccomp($total, self::MAX_DECIMAL_12_4, 4) > 0) {
                $validator->errors()->add('items', 'The order total is too large.');
            }
        });
    }
}
