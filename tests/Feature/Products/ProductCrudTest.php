<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;

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
