<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('creates one active reservation per item and reduces availableToSell', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 100, StockMovementType::Received);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 30],
    ]);

    $order = app(OrderService::class)->confirmOrder($order);

    expect($order->status->value)->toBe('confirmed');
    expect(StockReservation::where('order_item_id', $order->items[0]->id)->count())->toBe(1);
    expect($product->stock()->first()->availableToSell)->toBe(70);
});

it('subtracts existing reservations from another order, not just raw quantity, when validating availability', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 50, StockMovementType::Received);

    $orderService = app(OrderService::class);

    $order1 = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 40]]);
    $orderService->confirmOrder($order1);

    $order2 = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 20]]);

    expect(fn () => $orderService->confirmOrder($order2))
        ->toThrow(InsufficientStockException::class);
});

it('rolls back all reservations for an order when one item would exceed availability (all-or-nothing)', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $productA = Product::factory()->create(['tenant_id' => $tenant->id]);
    $productB = Product::factory()->create(['tenant_id' => $tenant->id]);
    $productC = Product::factory()->create(['tenant_id' => $tenant->id]);

    $stockService = app(StockService::class);
    $stockService->recordMovement($productA, $warehouse, 100, StockMovementType::Received);
    $stockService->recordMovement($productB, $warehouse, 5, StockMovementType::Received); // not enough
    $stockService->recordMovement($productC, $warehouse, 100, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [
        ['product_id' => $productA->id, 'quantity' => 10],
        ['product_id' => $productB->id, 'quantity' => 10], // exceeds available
        ['product_id' => $productC->id, 'quantity' => 10],
    ]);

    expect(fn () => $orderService->confirmOrder($order))
        ->toThrow(InsufficientStockException::class);

    expect(StockReservation::whereIn('order_item_id', $order->items->pluck('id'))->count())->toBe(0);
});

it('rejects confirming an already-confirmed or cancelled order with 409', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    $this->postJson("/api/v1/orders/{$order->id}/confirm")->assertOk();
    $this->postJson("/api/v1/orders/{$order->id}/confirm")->assertStatus(409);
});
