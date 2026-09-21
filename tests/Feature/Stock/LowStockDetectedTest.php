<?php

use App\Enums\StockMovementType;
use App\Events\LowStockDetected;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\Event;

it('fires LowStockDetected only on the movement that crosses the reorder point', function () {
    Event::fake([LowStockDetected::class]);

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(StockService::class);
    $service->setReorderPoint($product, $warehouse, 10);
    $service->recordMovement($product, $warehouse, 20, StockMovementType::Received); // 0 -> 20, above threshold
    $service->recordMovement($product, $warehouse, -15, StockMovementType::Sold);     // 20 -> 5, crosses: fires once
    $service->recordMovement($product, $warehouse, -2, StockMovementType::Sold);      // 5 -> 3, already below: no refire

    Event::assertDispatchedTimes(LowStockDetected::class, 1);
});

it('does not fire LowStockDetected when stock stays above the reorder point', function () {
    Event::fake([LowStockDetected::class]);

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(StockService::class);
    $service->setReorderPoint($product, $warehouse, 10);
    $service->recordMovement($product, $warehouse, 50, StockMovementType::Received);
    $service->recordMovement($product, $warehouse, -5, StockMovementType::Sold); // 50 -> 45, still above 10

    Event::assertNotDispatched(LowStockDetected::class);
});
