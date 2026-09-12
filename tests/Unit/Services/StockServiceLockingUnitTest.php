<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Scopes\TenantScope;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('creates the product_stock row at zero on first use and reuses the same row on a second call', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(StockService::class);
    $first = $service->lockStockRow($product, $warehouse);
    expect($first->quantity)->toBe(0);

    $second = $service->lockStockRow($product, $warehouse);
    expect($second->id)->toBe($first->id);

    expect(
        ProductStock::withoutGlobalScope(TenantScope::class)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->count()
    )->toBe(1);
});

it('runs recordMovement atomically — a rejected oversell leaves neither quantity nor a movement row behind', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    try {
        app(StockService::class)->recordMovement($product, $warehouse, -100, StockMovementType::Sold);
    } catch (InsufficientStockException) {
        //
    }

    expect($product->stock()->first()->quantity)->toBe(10);
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(1);
});

it('proves guard LOGIC only — not real concurrency (see tests/Concurrency/StockRowLockingTest for that)', function () {
    // Two back-to-back confirmOrder() calls, sequentially, in the same
    // connection/process. This proves the guard correctly rejects the
    // second when stock runs out — it does NOT prove a real concurrent
    // second transaction gets blocked by the row lock itself. That
    // distinct claim is only proven by the two-connection lock_timeout
    // test in tests/Concurrency/StockRowLockingTest.php.
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order1 = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 10]]);
    $orderService->confirmOrder($order1);

    $order2 = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);

    expect(fn () => $orderService->confirmOrder($order2))->toThrow(InsufficientStockException::class);
});
