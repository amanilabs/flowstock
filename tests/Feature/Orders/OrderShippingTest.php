<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

dataset('shippable_statuses', ['confirmed', 'processing']);

it('ships from confirmed or processing, fulfilling reservations and decrementing physical stock', function (string $status) {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 8]]);
    $order = $orderService->confirmOrder($order);

    if ($status === 'processing') {
        $order = $orderService->markProcessing($order);
    }

    $response = $this->postJson("/api/v1/orders/{$order->id}/ship")->assertOk();

    expect($response->json('data.status'))->toBe('shipped');
    expect($order->items[0]->reservation()->first()->status->value)->toBe('fulfilled');
    expect($product->stock()->first()->quantity)->toBe(12);
})->with('shippable_statuses');

it('records a StockMovement whose reference is the OrderItem, not the Order', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 8]]);
    $order = $orderService->confirmOrder($order);
    $orderService->shipOrder($order);

    $movement = StockMovement::where('type', 'sold')->first();

    expect($movement->reference_type)->toBe((new OrderItem)->getMorphClass());
    expect($movement->reference_id)->toBe($order->items[0]->id);
    expect($movement->quantity_change)->toBe(-8);
});
