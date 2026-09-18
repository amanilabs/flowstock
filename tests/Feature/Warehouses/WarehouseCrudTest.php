<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;

it('supports full CRUD as Admin, but only Admin', function () {
    $tenant = Tenant::factory()->create();

    foreach (['Manager', 'Staff'] as $role) {
        actingAsRole($role, $tenant);
        $this->postJson('/api/v1/warehouses', [
            'name' => 'WH', 'code' => "WH-$role", 'address_line1' => '1 St',
            'city' => 'City', 'postal_code' => '000', 'country' => 'US',
        ])->assertForbidden();
    }

    actingAsRole('Admin', $tenant);
    $create = $this->postJson('/api/v1/warehouses', [
        'name' => 'Main', 'code' => 'WH-01', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->putJson("/api/v1/warehouses/{$id}", ['name' => 'Main Updated'])
        ->assertOk()->assertJsonPath('data.name', 'Main Updated');

    $this->deleteJson("/api/v1/warehouses/{$id}")->assertNoContent();
});

it('enforces warehouse code uniqueness per tenant, not globally', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Warehouse::factory()->create(['tenant_id' => $tenantA->id, 'code' => 'SHARED']);

    actingAsRole('Admin', $tenantB);
    $this->postJson('/api/v1/warehouses', [
        'name' => 'WH', 'code' => 'SHARED', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertCreated();

    actingAsRole('Admin', $tenantA);
    $this->postJson('/api/v1/warehouses', [
        'name' => 'WH', 'code' => 'SHARED', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertUnprocessable();
});

it('filters the index by search matching name, code, or city', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    Warehouse::factory()->create(['tenant_id' => $tenant->id, 'name' => 'North Depot', 'code' => 'NORTH-1', 'city' => 'Chicago']);
    Warehouse::factory()->create(['tenant_id' => $tenant->id, 'name' => 'South Depot', 'code' => 'SOUTH-1', 'city' => 'Miami']);

    $byName = $this->getJson('/api/v1/warehouses?search=north')->assertOk();
    expect($byName->json('data'))->toHaveCount(1);
    expect($byName->json('data.0.name'))->toBe('North Depot');

    $byCity = $this->getJson('/api/v1/warehouses?search=miami')->assertOk();
    expect($byCity->json('data'))->toHaveCount(1);
    expect($byCity->json('data.0.city'))->toBe('Miami');
});

it('reports product_count and total_stock summed across all stocked products', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $productA = Product::factory()->create(['tenant_id' => $tenant->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($productA, $warehouse, 10, StockMovementType::Received);
    $stockService->recordMovement($productB, $warehouse, 5, StockMovementType::Received);

    $response = $this->getJson('/api/v1/warehouses')->assertOk();

    expect($response->json('data.0.product_count'))->toBe(2);
    expect($response->json('data.0.total_stock'))->toBe(15);
});
