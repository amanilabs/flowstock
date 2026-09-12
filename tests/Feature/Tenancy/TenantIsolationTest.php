<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Tenant;
use App\Models\Warehouse;

it('never shows another tenant\'s products, warehouses, customers, or orders in index lists', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

    actingAsRole('Admin', $tenantA);

    $this->getJson('/api/v1/products')->assertJsonMissing(['sku' => $productB->sku]);
    $this->getJson('/api/v1/warehouses')->assertJsonMissing(['code' => $warehouseB->code]);
    $this->getJson('/api/v1/customers')->assertJsonMissing(['id' => $customerB->id]);
});

it('404s cross-tenant route-model-binding access as a side effect of TenantScope, not controller code', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $orderB = Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
        'warehouse_id' => $warehouseB->id,
    ]);

    actingAsRole('Admin', $tenantA);

    $this->getJson("/api/v1/products/{$productB->id}")->assertNotFound();
    $this->getJson("/api/v1/warehouses/{$warehouseB->id}")->assertNotFound();
    $this->getJson("/api/v1/customers/{$customerB->id}")->assertNotFound();
    $this->getJson("/api/v1/orders/{$orderB->id}")->assertNotFound();
});

it('uses the correct singular product_stock table name, not Eloquent\'s auto-pluralized guess', function () {
    expect((new ProductStock)->getTable())->toBe('product_stock');
});
