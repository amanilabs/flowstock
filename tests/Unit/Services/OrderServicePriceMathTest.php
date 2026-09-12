<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;

it('computes exact decimal totals via bcmul/bcadd, not float math that would drift', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    // Chosen so naive float multiplication would visibly drift from the
    // exact decimal result.
    $productA = Product::factory()->create(['tenant_id' => $tenant->id, 'selling_price' => 19.9999]);
    $productB = Product::factory()->create(['tenant_id' => $tenant->id, 'selling_price' => 0.1]);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $productA->id, 'quantity' => 3],
        ['product_id' => $productB->id, 'quantity' => 3],
    ]);

    $items = $order->items;

    expect((string) $items[0]->subtotal)->toBe('59.9997');
    expect((string) $items[1]->subtotal)->toBe('0.3000');
    expect((string) $order->total_amount)->toBe('60.2997');
});
