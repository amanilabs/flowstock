<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

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

it('filters customers by search and reports order_count and total_spent', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $alice = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Anderson', 'email' => 'alice@example.com']);
    $bob = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Builder', 'email' => 'bob@example.com']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order1 = $orderService->createOrder($alice, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $orderService->confirmOrder($order1);
    $order2 = $orderService->createOrder($alice, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $orderService->confirmOrder($order2);
    $orderService->cancelOrder($order2);

    $bySearch = $this->getJson('/api/v1/customers?search=alice')->assertOk();
    expect($bySearch->json('data'))->toHaveCount(1);
    expect($bySearch->json('data.0.name'))->toBe('Alice Anderson');
    expect($bySearch->json('data.0.order_count'))->toBe(2);
    expect((float) $bySearch->json('data.0.total_spent'))->toBe((float) $order1->total_amount);

    $byEmail = $this->getJson('/api/v1/customers?search=bob@example.com')->assertOk();
    expect($byEmail->json('data'))->toHaveCount(1);
    expect($byEmail->json('data.0.id'))->toBe($bob->id);
});
