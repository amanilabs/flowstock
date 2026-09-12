<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

dataset('cancellable_statuses', ['pending', 'confirmed', 'processing']);

it('cancels legally from pending, confirmed, and processing, releasing any active reservations', function (string $status) {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 5]]);

    if (in_array($status, ['confirmed', 'processing'], true)) {
        $order = $orderService->confirmOrder($order);
    }
    if ($status === 'processing') {
        $order = $orderService->markProcessing($order);
    }

    $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'test reason'])
        ->assertOk();

    expect($response->json('data.status'))->toBe('cancelled');

    if ($status !== 'pending') {
        $reservation = $order->items[0]->reservation()->first();
        expect($reservation)->not->toBeNull();
        expect($reservation->status->value)->toBe('released');
    }
})->with('cancellable_statuses');

it('rejects cancelling a shipped or delivered order with 409', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 5]]);
    $order = $orderService->confirmOrder($order);
    $order = $orderService->shipOrder($order);

    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertStatus(409);
});

it('creates no StockMovement when cancelling a confirmed order — physical stock never left', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 5]]);
    $order = $orderService->confirmOrder($order);

    $countBefore = StockMovement::count();
    $orderService->cancelOrder($order);
    $countAfter = StockMovement::count();

    expect($countAfter)->toBe($countBefore);
});

it('appends the cancellation reason to the order notes', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [['product_id' => $product->id, 'quantity' => 1]]);

    $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Customer changed their mind'])
        ->assertOk();

    expect($response->json('data.notes'))->toContain('Customer changed their mind');
});
