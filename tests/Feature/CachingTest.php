<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\Warehouse;

it('invalidates the cached product list after create, update, and delete', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Widget']);
    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

    // The list above was created outside the HTTP layer, so it never went
    // through ProductController::store()'s flush — prime the cache, then
    // prove a real write through the API invalidates it.
    $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');

    $this->postJson('/api/v1/products', [
        'category_id' => null,
        'sku' => 'SKU-1',
        'name' => 'Gadget',
        'unit_of_measure' => 'each',
        'selling_price' => 10,
        'cost_price' => 5,
        'reorder_point' => 0,
    ])->assertCreated();

    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(2, 'data');

    $this->putJson("/api/v1/products/{$product->id}", ['name' => 'Renamed Widget'])->assertOk();
    $names = collect($this->getJson('/api/v1/products')->json('data'))->pluck('name');
    expect($names)->toContain('Renamed Widget');

    $this->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();
    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
});

it('flushes the product list cache when its category changes', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $category = ProductCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Category']);
    Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

    $this->getJson('/api/v1/products')->assertOk();

    $this->putJson("/api/v1/product-categories/{$category->id}", ['name' => 'New Category'])->assertOk();

    $categoryNames = collect($this->getJson('/api/v1/products')->json('data'))->pluck('category.name');
    expect($categoryNames)->toContain('New Category');
});

it('invalidates the cached warehouse list after create and update', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $this->getJson('/api/v1/warehouses')->assertOk()->assertJsonCount(0, 'data');

    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $this->postJson('/api/v1/warehouses', [
        'code' => 'WH-CACHE',
        'name' => 'Cache Warehouse',
        'address_line1' => '123 Main St',
        'city' => 'Springfield',
        'postal_code' => '00000',
        'country' => 'US',
        'is_active' => true,
    ])->assertCreated();

    $this->getJson('/api/v1/warehouses')->assertOk()->assertJsonCount(2, 'data');

    $this->putJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'Renamed Warehouse'])->assertOk();
    $names = collect($this->getJson('/api/v1/warehouses')->json('data'))->pluck('name');
    expect($names)->toContain('Renamed Warehouse');
});

it('never leaks one tenant\'s cache invalidation into another tenant\'s list', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Product::factory()->create(['tenant_id' => $tenantB->id]);

    actingAsRole('Admin', $tenantA);
    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

    $this->postJson('/api/v1/products', [
        'category_id' => null,
        'sku' => 'SKU-A',
        'name' => 'Tenant A Product',
        'unit_of_measure' => 'each',
        'selling_price' => 10,
        'cost_price' => 5,
        'reorder_point' => 0,
    ])->assertCreated();

    actingAsRole('Admin', $tenantB);
    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
});
