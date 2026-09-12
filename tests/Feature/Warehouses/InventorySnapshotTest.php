<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;

it('returns stock scoped to the requesting tenant even when another tenant has stock for a similarly-numbered product', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $productA = Product::factory()->create(['tenant_id' => $tenantA->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenantA->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id]);

    $service = app(StockService::class);
    $service->recordMovement($productA, $warehouseA, 40, StockMovementType::Received);
    $service->recordMovement($productB, $warehouseB, 999, StockMovementType::Received);

    actingAsRole('Admin', $tenantA);
    $response = $this->getJson("/api/v1/products/{$productA->id}/stock")->assertOk();

    expect($response->json('data.0.quantity'))->toBe(40);
    expect($response->json('data'))->toHaveCount(1);
});
