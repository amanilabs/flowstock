<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('lists inventory across every product and warehouse', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $productA = Product::factory()->create(['tenant_id' => $tenant->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($productA, $warehouse, 10, StockMovementType::Received);
    $stockService->recordMovement($productB, $warehouse, 5, StockMovementType::Received);

    $response = $this->getJson('/api/v1/stock')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('reports reserved_quantity and available_quantity correctly', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    // Confirming an order is the real path that creates an active
    // StockReservation — reusing it here instead of inserting one directly.
    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 6],
    ]);
    $orderService->confirmOrder($order);

    $response = $this->getJson('/api/v1/stock')->assertOk();

    expect($response->json('data.0.quantity'))->toBe(20);
    expect($response->json('data.0.reserved_quantity'))->toBe(6);
    expect($response->json('data.0.available_quantity'))->toBe(14);
});

it('filters inventory by warehouse_id, product_id, category_id, low_stock, and search', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $category = ProductCategory::factory()->create(['tenant_id' => $tenant->id]);
    $lowStockProduct = Product::factory()->create([
        'tenant_id' => $tenant->id, 'name' => 'Widget Low', 'category_id' => $category->id, 'reorder_point' => 10,
    ]);
    $healthyProduct = Product::factory()->create([
        'tenant_id' => $tenant->id, 'name' => 'Gadget Healthy', 'reorder_point' => 5,
    ]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($lowStockProduct, $warehouseA, 3, StockMovementType::Received); // below reorder_point 10
    $stockService->recordMovement($healthyProduct, $warehouseB, 50, StockMovementType::Received); // well above reorder_point 5

    $this->getJson("/api/v1/stock?warehouse_id={$warehouseA->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'Widget Low');

    $this->getJson("/api/v1/stock?product_id={$healthyProduct->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'Gadget Healthy');

    $this->getJson("/api/v1/stock?category_id={$category->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'Widget Low');

    $this->getJson('/api/v1/stock?low_stock=1')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'Widget Low');

    $this->getJson('/api/v1/stock?search=gadget')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'Gadget Healthy');
});

it('lists movement history for a product, optionally scoped to a warehouse', function () {
    $tenant = Tenant::factory()->create();
    $admin = actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($product, $warehouseA, 10, StockMovementType::Received, userId: $admin->id);
    $stockService->recordMovement($product, $warehouseB, 4, StockMovementType::Received, userId: $admin->id);

    $all = $this->getJson("/api/v1/products/{$product->id}/stock/movements")->assertOk();
    expect($all->json('data'))->toHaveCount(2);
    expect($all->json('data.0.user.name'))->toBe($admin->name);

    $scoped = $this->getJson("/api/v1/products/{$product->id}/stock/movements?warehouse_id={$warehouseA->id}")->assertOk();
    expect($scoped->json('data'))->toHaveCount(1);
    expect($scoped->json('data.0.warehouse_id'))->toBe($warehouseA->id);
});
