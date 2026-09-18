<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;

it('supports full CRUD as Admin', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $create = $this->postJson('/api/v1/products', [
        'sku' => 'ABC-1', 'name' => 'Widget', 'unit_of_measure' => 'pcs',
        'cost_price' => 5, 'selling_price' => 10,
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->getJson("/api/v1/products/{$id}")->assertOk()->assertJsonPath('data.sku', 'ABC-1');

    $this->putJson("/api/v1/products/{$id}", ['name' => 'Widget v2'])
        ->assertOk()->assertJsonPath('data.name', 'Widget v2');

    $this->deleteJson("/api/v1/products/{$id}")->assertNoContent();
    $this->getJson("/api/v1/products/{$id}")->assertNotFound();
});

it('enforces SKU uniqueness per tenant, not globally', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Product::factory()->create(['tenant_id' => $tenantA->id, 'sku' => 'SHARED-SKU']);

    actingAsRole('Admin', $tenantB);
    $this->postJson('/api/v1/products', [
        'sku' => 'SHARED-SKU', 'name' => 'Widget', 'unit_of_measure' => 'pcs',
        'cost_price' => 5, 'selling_price' => 10,
    ])->assertCreated();

    actingAsRole('Admin', $tenantA);
    $this->postJson('/api/v1/products', [
        'sku' => 'SHARED-SKU', 'name' => 'Widget', 'unit_of_measure' => 'pcs',
        'cost_price' => 5, 'selling_price' => 10,
    ])->assertUnprocessable();
});

it('rejects a category_id belonging to another tenant instead of silently accepting it', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $categoryB = ProductCategory::factory()->create(['tenant_id' => $tenantB->id]);

    actingAsRole('Admin', $tenantA);
    $this->postJson('/api/v1/products', [
        'sku' => 'X-1', 'name' => 'Widget', 'unit_of_measure' => 'pcs',
        'cost_price' => 5, 'selling_price' => 10, 'category_id' => $categoryB->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

it('excludes soft-deleted products from index and show', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    $this->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();

    $this->getJson('/api/v1/products')->assertJsonMissing(['sku' => $product->sku]);
    $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
});

it('computes margin and marginPercentage correctly', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id, 'cost_price' => 10, 'selling_price' => 15,
    ]);

    expect((float) $product->margin)->toBe(5.0);
    expect((float) $product->marginPercentage)->toBe(50.0);
});

it('filters the index by search matching name or SKU', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Blue Widget', 'sku' => 'BW-1']);
    Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Red Gadget', 'sku' => 'RG-2']);

    $byName = $this->getJson('/api/v1/products?search=widget')->assertOk();
    expect($byName->json('data'))->toHaveCount(1);
    expect($byName->json('data.0.name'))->toBe('Blue Widget');

    $bySku = $this->getJson('/api/v1/products?search=RG-2')->assertOk();
    expect($bySku->json('data'))->toHaveCount(1);
    expect($bySku->json('data.0.sku'))->toBe('RG-2');
});

it('filters the index by category_id', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $categoryA = ProductCategory::factory()->create(['tenant_id' => $tenant->id]);
    $categoryB = ProductCategory::factory()->create(['tenant_id' => $tenant->id]);
    Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $categoryA->id]);
    Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $categoryB->id]);

    $response = $this->getJson("/api/v1/products?category_id={$categoryA->id}")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.category.id'))->toBe($categoryA->id);
});

it('reports total_stock summed across every warehouse on the index', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($product, $warehouseA, 10, StockMovementType::Received);
    $stockService->recordMovement($product, $warehouseB, 7, StockMovementType::Received);

    $response = $this->getJson('/api/v1/products')->assertOk();

    expect($response->json('data.0.total_stock'))->toBe(17);
});
