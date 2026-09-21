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

it('orders rows by product name so a product\'s warehouses stay adjacent for grouping in the UI', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $productZ = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zebra Widget']);
    $productA = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Anchor Widget']);
    $warehouse1 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse2 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    // Deliberately interleaved insert order — the response must still come
    // back grouped by product name, not insertion order.
    $stockService->recordMovement($productZ, $warehouse1, 10, StockMovementType::Received);
    $stockService->recordMovement($productA, $warehouse2, 10, StockMovementType::Received);
    $stockService->recordMovement($productZ, $warehouse2, 10, StockMovementType::Received);
    $stockService->recordMovement($productA, $warehouse1, 10, StockMovementType::Received);

    $response = $this->getJson('/api/v1/stock')->assertOk();
    $productIds = collect($response->json('data'))->pluck('product.id')->all();

    expect($productIds)->toBe([$productA->id, $productA->id, $productZ->id, $productZ->id]);
});

it('excludes a soft-deleted product\'s leftover stock row instead of 500ing', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $keptProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $deletedProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($keptProduct, $warehouse, 10, StockMovementType::Received);
    $stockService->recordMovement($deletedProduct, $warehouse, 10, StockMovementType::Received);

    // A raw join to products bypasses Eloquent's soft-delete scope — this is
    // the exact scenario that used to 500 (InventoryResource reading
    // ->product->id off a null relation for the deleted product's row).
    $deletedProduct->delete();

    $response = $this->getJson('/api/v1/stock')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.product.id'))->toBe($keptProduct->id);
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
        'tenant_id' => $tenant->id, 'name' => 'Widget Low', 'category_id' => $category->id,
    ]);
    $healthyProduct = Product::factory()->create([
        'tenant_id' => $tenant->id, 'name' => 'Gadget Healthy',
    ]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->setReorderPoint($lowStockProduct, $warehouseA, 10);
    $stockService->setReorderPoint($healthyProduct, $warehouseB, 5);
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

it('treats reorder_point as independent per warehouse for the same product', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse1 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse2 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($product, $warehouse1, 20, StockMovementType::Received);
    $stockService->recordMovement($product, $warehouse2, 20, StockMovementType::Received);
    $stockService->setReorderPoint($product, $warehouse1, 10);
    $stockService->setReorderPoint($product, $warehouse2, 30);

    $response = $this->getJson("/api/v1/stock?product_id={$product->id}")->assertOk();
    $rows = collect($response->json('data'))->keyBy(fn ($row) => $row['warehouse']['id']);

    expect($rows[$warehouse1->id]['reorder_point'])->toBe(10);
    expect($rows[$warehouse1->id]['status'])->toBe('in_stock');
    expect($rows[$warehouse2->id]['reorder_point'])->toBe(30);
    expect($rows[$warehouse2->id]['status'])->toBe('low_stock');
});

it('lets an authorized user set a warehouse-specific reorder point, creating the row if needed', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    // No stock movement has ever happened here — no product_stock row exists yet.
    $response = $this->putJson("/api/v1/products/{$product->id}/stock/{$warehouse->id}", [
        'reorder_point' => 15,
    ])->assertOk();

    expect($response->json('data.reorder_point'))->toBe(15);
    expect($response->json('data.quantity'))->toBe(0);

    $this->putJson("/api/v1/products/{$product->id}/stock/{$warehouse->id}", [
        'reorder_point' => -1,
    ])->assertUnprocessable()->assertJsonValidationErrors('reorder_point');
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
