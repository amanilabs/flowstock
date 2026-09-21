<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;

dataset('permission_boundaries', function () {
    return [
        'Staff can view products' => ['Staff', 'GET', fn () => '/api/v1/products', 200],
        'Staff cannot create products' => ['Staff', 'POST', fn () => '/api/v1/products', 403],
        'Manager can create products' => ['Manager', 'POST', fn () => '/api/v1/products', 201],
        'Staff cannot create warehouses' => ['Staff', 'POST', fn () => '/api/v1/warehouses', 403],
        'Manager cannot create warehouses' => ['Manager', 'POST', fn () => '/api/v1/warehouses', 403],
        'Admin can create warehouses' => ['Admin', 'POST', fn () => '/api/v1/warehouses', 201],
        'Staff cannot manage categories' => ['Staff', 'POST', fn () => '/api/v1/product-categories', 403],
        'Staff can adjust stock' => ['Staff', 'POST', fn (Product $p, Warehouse $w) => "/api/v1/products/{$p->id}/stock/adjust", 201],
        'Staff can set a warehouse reorder point' => ['Staff', 'PUT', fn (Product $p, Warehouse $w) => "/api/v1/products/{$p->id}/stock/{$w->id}", 200],
    ];
});

it('enforces role/endpoint permission boundaries', function (string $role, string $method, Closure $urlFactory, int $expectedStatus) {
    $tenant = Tenant::factory()->create();
    actingAsRole($role, $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $url = $urlFactory($product, $warehouse);

    $payload = match (true) {
        str_ends_with($url, '/products') && $method === 'POST' => [
            'sku' => 'SKU-1', 'name' => 'Test', 'unit_of_measure' => 'pcs', 'cost_price' => 1, 'selling_price' => 2,
        ],
        str_ends_with($url, '/warehouses') && $method === 'POST' => [
            'name' => 'WH', 'code' => 'WH-X', 'address_line1' => '1 St', 'city' => 'City', 'postal_code' => '000', 'country' => 'US',
        ],
        str_ends_with($url, '/product-categories') && $method === 'POST' => [
            'name' => 'Cat', 'slug' => 'cat',
        ],
        str_contains($url, '/stock/adjust') => [
            'warehouse_id' => $warehouse->id, 'quantity_change' => 10, 'type' => 'received',
        ],
        $method === 'PUT' && str_contains($url, '/stock/') => [
            'reorder_point' => 15,
        ],
        default => [],
    };

    $this->json($method, $url, $payload)->assertStatus($expectedStatus);
})->with('permission_boundaries');

it('gives Manager cancel-orders but not refund-orders, mirroring the manage-warehouses precedent', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    actingAsRole('Admin', $tenant);
    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    actingAsRole('Manager', $tenant);
    $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

    $order2 = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);
    actingAsRole('Manager', $tenant);
    $this->postJson("/api/v1/orders/{$order2->id}/refund")->assertForbidden();
});

it('returns 401 with no token at all', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});
