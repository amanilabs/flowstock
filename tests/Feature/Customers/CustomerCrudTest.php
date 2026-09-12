<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;

it('supports full CRUD and excludes soft-deleted customers afterward', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $create = $this->postJson('/api/v1/customers', ['name' => 'Bob Buyer'])->assertCreated();
    $id = $create->json('data.id');

    $this->putJson("/api/v1/customers/{$id}", ['name' => 'Bob B. Buyer'])
        ->assertOk()->assertJsonPath('data.name', 'Bob B. Buyer');

    $this->deleteJson("/api/v1/customers/{$id}")->assertNoContent();
    $this->getJson("/api/v1/customers/{$id}")->assertNotFound();
    $this->getJson('/api/v1/customers')->assertJsonMissing(['id' => $id]);
});

it('rejects creating an order for a soft-deleted customer', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    $customer->delete();

    $this->postJson('/api/v1/orders', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
});
