<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;

it('creates a movement and updates quantity for a positive adjustment via the API', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => 25, 'type' => 'received',
    ])->assertCreated();

    expect(StockMovement::where('product_id', $product->id)->count())->toBe(1);
    expect($product->stock()->first()->quantity)->toBe(25);
});

it('rejects a zero quantity_change at the validation layer, before it reaches the service', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouse->id, 'quantity_change' => 0, 'type' => 'received',
    ])->assertUnprocessable()->assertJsonValidationErrors('quantity_change');
});

it('rejects a warehouse_id belonging to another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $product = Product::factory()->create(['tenant_id' => $tenantA->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id]);

    actingAsRole('Admin', $tenantA);
    $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
        'warehouse_id' => $warehouseB->id, 'quantity_change' => 10, 'type' => 'received',
    ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
});

it('never bypasses InsufficientStockException for the Adjustment movement type', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    $service = app(StockService::class);
    $service->recordMovement($product, $warehouse, 5, StockMovementType::Received);

    expect(fn () => $service->recordMovement($product, $warehouse, -10, StockMovementType::Adjustment))
        ->toThrow(InsufficientStockException::class);

    expect($product->stock()->first()->quantity)->toBe(5);
});

it('leaves manual adjustments with a null polymorphic reference', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    app(StockService::class)->recordMovement($product, $warehouse, 5, StockMovementType::Received);

    $movement = StockMovement::first();
    expect($movement->reference_type)->toBeNull();
    expect($movement->reference_id)->toBeNull();
});
