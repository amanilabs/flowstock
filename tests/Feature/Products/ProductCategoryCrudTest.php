<?php

use App\Models\ProductCategory;
use App\Models\Tenant;

it('supports full CRUD as Admin', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $create = $this->postJson('/api/v1/product-categories', [
        'name' => 'Electronics', 'slug' => 'electronics',
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->putJson("/api/v1/product-categories/{$id}", ['name' => 'Electronics & Gadgets'])
        ->assertOk()->assertJsonPath('data.name', 'Electronics & Gadgets');

    $this->deleteJson("/api/v1/product-categories/{$id}")->assertNoContent();
});

it('rejects a parent_id belonging to another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $parentB = ProductCategory::factory()->create(['tenant_id' => $tenantB->id]);

    actingAsRole('Admin', $tenantA);
    $this->postJson('/api/v1/product-categories', [
        'name' => 'Sub', 'slug' => 'sub', 'parent_id' => $parentB->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

it('enforces slug uniqueness per tenant, and updating without changing the slug does not 422 against itself', function () {
    $tenant = Tenant::factory()->create();
    $category = ProductCategory::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'books']);

    actingAsRole('Admin', $tenant);

    $this->postJson('/api/v1/product-categories', ['name' => 'Books 2', 'slug' => 'books'])
        ->assertUnprocessable();

    $this->putJson("/api/v1/product-categories/{$category->id}", ['name' => 'Books Updated', 'slug' => 'books'])
        ->assertOk();
});
