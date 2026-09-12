<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;

it('creates a pending order with correct order_number format and items', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'selling_price' => 9.99]);

    actingAsRole('Admin', $tenant);
    $response = $this->postJson('/api/v1/orders', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
    ])->assertCreated();

    expect($response->json('data.order_number'))->toBe('ORD-000001');
    expect($response->json('data.status'))->toBe('pending');
    expect($response->json('data.items.0.unit_price'))->toBe('9.9900');
});

it('gives each tenant its own independent order number sequence starting at 1', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    foreach ([$tenantA, $tenantB] as $tenant) {
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        actingAsRole('Admin', $tenant);
        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        expect($response->json('data.order_number'))->toBe('ORD-000001');
    }
});

it('honors an explicit unit_price override instead of the product default', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'selling_price' => 9.99]);

    actingAsRole('Admin', $tenant);
    $response = $this->postJson('/api/v1/orders', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 5.00]],
    ])->assertCreated();

    expect($response->json('data.items.0.unit_price'))->toBe('5.0000');
});

it('rejects cross-tenant customer_id, warehouse_id, and product_id, and an empty items array', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenantA->id]);
    $productA = Product::factory()->create(['tenant_id' => $tenantA->id]);

    actingAsRole('Admin', $tenantA);

    $this->postJson('/api/v1/orders', [
        'customer_id' => $customerB->id, 'warehouse_id' => $warehouseA->id,
        'items' => [['product_id' => $productA->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');

    $this->postJson('/api/v1/orders', [
        'customer_id' => $customerA->id, 'warehouse_id' => $warehouseB->id,
        'items' => [['product_id' => $productA->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');

    $this->postJson('/api/v1/orders', [
        'customer_id' => $customerA->id, 'warehouse_id' => $warehouseA->id,
        'items' => [['product_id' => $productB->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

    $this->postJson('/api/v1/orders', [
        'customer_id' => $customerA->id, 'warehouse_id' => $warehouseA->id,
        'items' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');
});
