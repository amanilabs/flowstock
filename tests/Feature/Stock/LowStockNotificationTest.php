<?php

use App\Enums\StockMovementType;
use App\Events\LowStockDetected;
use App\Listeners\NotifyLowStock;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

it('broadcasts and emails only the correct tenant\'s view-stock users, never another tenant\'s', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $admin = actingAsRole('Admin', $tenant); // view-stock
    $manager = User::factory()->create(['tenant_id' => $tenant->id]);
    $manager->assignRole('Manager'); // view-stock
    $staff = User::factory()->create(['tenant_id' => $tenant->id]);
    $staff->assignRole('Staff'); // view-stock too, per RoleSeeder

    $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($otherTenant->id);
    $otherAdmin->assignRole('Admin');

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $event = new LowStockDetected($product, $warehouse, $tenant->id, 20, 5, 10);
    (new NotifyLowStock)->handle($event);

    Notification::assertSentTo([$admin, $manager, $staff], LowStockNotification::class);
    Notification::assertNotSentTo($otherAdmin, LowStockNotification::class);
});

it('includes both mail and broadcast in the notification channels', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new LowStockNotification($product, $warehouse, 5, 10);

    expect($notification->via(new User))->toBe(['mail', 'broadcast']);
    expect($notification->broadcastType())->toBe('LowStockAlert');
});

it('broadcasts product, warehouse, the real available quantity, and the reorder point', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 20, StockMovementType::Received);

    // Reserve 6 via the real order flow so available_quantity (20 - 6 = 14)
    // is provably different from the raw post-movement quantity (20) —
    // proving the broadcast doesn't just relabel newQuantity.
    $orderService = app(OrderService::class);
    $order = $orderService->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 6],
    ]);
    $orderService->confirmOrder($order);

    $notification = new LowStockNotification($product, $warehouse, 20, 10);
    $message = $notification->toBroadcast(new User)->data;

    expect($message)->toBe([
        'product' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
        'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name],
        'available_quantity' => 14,
        'reorder_point' => 10,
    ]);
});

it('authorizes a user for their own private channel but not someone else\'s', function () {
    // Channel auth is a pure local HMAC signature over the configured app
    // key/secret — no live Reverb connection needed — but phpunit.xml pins
    // BROADCAST_CONNECTION=null for the suite so other tests never try to
    // reach a real broadcaster. Use the real driver for this one. Switching
    // the default here resolves a *fresh* 'reverb' broadcaster instance
    // with an empty channel registry (routes/channels.php only ran once, at
    // boot, against the 'null' instance) — re-require it so the channel
    // rule is registered on the instance Broadcast::auth() will actually use.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');

    $tenant = Tenant::factory()->create();
    $user = actingAsRole('Admin', $tenant);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-App.Models.User.{$user->id}",
    ])->assertOk()->assertJsonStructure(['auth']);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-App.Models.User.{$otherUser->id}",
    ])->assertForbidden();
});

it('rejects a broadcasting auth request with no token at all', function () {
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-App.Models.User.1',
    ])->assertUnauthorized();
});
