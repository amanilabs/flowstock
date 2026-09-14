<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;
use Spatie\Activitylog\Models\Activity;

it('logs a product update with the diff and correct tenant_id', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $activity = Activity::where('subject_type', Product::class)->where('subject_id', $product->id)->orderByDesc('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->tenant_id)->toBe($tenant->id);
    expect($activity->attribute_changes['attributes']['name'])->toBe('New Name');
    expect($activity->attribute_changes['old']['name'])->toBe('Old Name');
});

it('logs a customer update with the correct tenant_id', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Customer']);
    $customer->update(['name' => 'New Customer']);

    $activity = Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->orderByDesc('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->tenant_id)->toBe($tenant->id);
});

it('logs an order status change (confirm) via the normal update() diff', function () {
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

    $activity = Activity::where('subject_type', Order::class)
        ->where('subject_id', $order->id)
        ->orderByDesc('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->tenant_id)->toBe($tenant->id);
    expect($activity->attribute_changes['attributes']['status'])->toBe('confirmed');
});

it('logs a failed login attempt', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'audit@test.com']);

    $this->postJson('/api/v1/login', ['email' => 'audit@test.com', 'password' => 'wrong-password'])
        ->assertUnprocessable();

    $activity = Activity::where('log_name', 'auth')->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('login failed');
    expect($activity->properties['succeeded'])->toBeFalse();
});

it('logs a successful login attempt with the correct tenant_id', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'audit2@test.com',
        'password' => bcrypt('correct-password'),
    ]);

    $this->postJson('/api/v1/login', ['email' => 'audit2@test.com', 'password' => 'correct-password'])
        ->assertOk();

    $activity = Activity::where('log_name', 'auth')->latest()->first();

    expect($activity->description)->toBe('login succeeded');
    expect($activity->tenant_id)->toBe($tenant->id);
});

it('does not duplicate stock adjustments into the general activity log', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    expect(StockMovement::count())->toBe(1);
    expect(Activity::where('subject_type', ProductStock::class)->count())->toBe(0);
});
