<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('returns 422 for a direct stock-adjust oversell, confirming the exception-to-HTTP mapping end-to-end', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => -11, 'type' => 'sold',
    ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Insufficient'));
});

it('returns 422 for the confirm-order path too — same exception, same status code contract', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 5, StockMovementType::Received);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 6],
    ]);

    $this->postJson("/api/v1/orders/{$order->id}/confirm")->assertStatus(422);
});

it('allows requesting exactly what is available but rejects one unit more', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => -10, 'type' => 'sold',
    ])->assertCreated();

    expect($product->stock()->first()->quantity)->toBe(0);

    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => -1, 'type' => 'sold',
    ])->assertStatus(422);
});

it('creates the product_stock row at zero on first use, then correctly rejects a negative movement', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => -1, 'type' => 'sold',
    ])->assertStatus(422);
});
