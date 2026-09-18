<?php

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('filters orders by search matching order number or customer name, and reports items_count', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $alice = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Anderson']);
    $bob = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Builder']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $productA = Product::factory()->create(['tenant_id' => $tenant->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenant->id]);

    $orderService = app(OrderService::class);
    $aliceOrder = $orderService->createOrder($alice, $warehouse, [
        ['product_id' => $productA->id, 'quantity' => 1],
        ['product_id' => $productB->id, 'quantity' => 2],
    ]);
    $orderService->createOrder($bob, $warehouse, [['product_id' => $productA->id, 'quantity' => 1]]);

    $bySearch = $this->getJson('/api/v1/orders?search=alice')->assertOk();
    expect($bySearch->json('data'))->toHaveCount(1);
    expect($bySearch->json('data.0.customer.name'))->toBe('Alice Anderson');
    expect($bySearch->json('data.0.items_count'))->toBe(2);

    $byOrderNumber = $this->getJson("/api/v1/orders?search={$aliceOrder->order_number}")->assertOk();
    expect($byOrderNumber->json('data'))->toHaveCount(1);
    expect($byOrderNumber->json('data.0.id'))->toBe($aliceOrder->id);
});

it('filters orders by status', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $pending = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $toConfirm = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $orderService->confirmOrder($toConfirm);

    $pendingOnly = $this->getJson('/api/v1/orders?status=pending')->assertOk();
    expect($pendingOnly->json('data'))->toHaveCount(1);
    expect($pendingOnly->json('data.0.id'))->toBe($pending->id);

    $confirmedOnly = $this->getJson('/api/v1/orders?status=confirmed')->assertOk();
    expect($confirmedOnly->json('data'))->toHaveCount(1);
    expect($confirmedOnly->json('data.0.id'))->toBe($toConfirm->id);
});

it('lists orders newest first', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $orderService = app(OrderService::class);
    $first = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $second = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);
    $third = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);

    $response = $this->getJson('/api/v1/orders')->assertOk();

    expect($response->json('data.0.id'))->toBe($third->id);
    expect($response->json('data.1.id'))->toBe($second->id);
    expect($response->json('data.2.id'))->toBe($first->id);
});

it('rejects an invalid lifecycle transition with a 409 and does not mutate the order', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    // Still pending — shipping directly is not a valid transition.
    $this->postJson("/api/v1/orders/{$order->id}/ship")->assertStatus(409);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});
