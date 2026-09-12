<?php

use App\Enums\StockMovementType;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

it('rejects every illegal transition via the same InvalidOrderTransitionException', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);

    // Pending can't ship or deliver directly.
    expect(fn () => $orderService->shipOrder($order))->toThrow(InvalidOrderTransitionException::class);
    expect(fn () => $orderService->markDelivered($order))->toThrow(InvalidOrderTransitionException::class);

    $order = $orderService->confirmOrder($order);
    $order = $orderService->shipOrder($order);
    $order = $orderService->markDelivered($order);

    // Delivered is terminal except for refund.
    expect(fn () => $orderService->cancelOrder($order))->toThrow(InvalidOrderTransitionException::class);
    expect(fn () => $orderService->confirmOrder($order))->toThrow(InvalidOrderTransitionException::class);
});
