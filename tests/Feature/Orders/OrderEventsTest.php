<?php

use App\Enums\StockMovementType;
use App\Events\OrderConfirmed;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Support\Facades\Event;

it('fires OrderConfirmed when an order is confirmed', function () {
    Event::fake([OrderConfirmed::class]);

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    app(OrderService::class)->confirmOrder($order);

    Event::assertDispatched(OrderConfirmed::class, fn ($event) => $event->order->id === $order->id);
});
