<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// No RefreshDatabase here (see tests/Pest.php) — every test in this file
// creates and commits real rows, then cleans up manually in afterEach.
// Deleting the tenant cascades to everything else created for it.
afterEach(function () {
    DB::connection('pgsql')->table('tenants')->delete();
});

it('genuinely blocks a second independent connection from locking the same product_stock row until the first commits', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 50, StockMovementType::Received);

    $stockRowId = DB::connection('pgsql')->table('product_stock')
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->value('id');

    // Connection A: lock the row, deliberately don't commit yet.
    DB::connection('pgsql')->beginTransaction();
    DB::connection('pgsql')->table('product_stock')->where('id', $stockRowId)->lockForUpdate()->first();

    // Connection B: a genuinely separate session to the same database.
    // A short lock_timeout makes the block deterministic to assert on,
    // rather than timing-sensitive.
    DB::connection('pgsql_secondary')->statement("SET lock_timeout = '250ms'");

    $blocked = false;
    try {
        DB::connection('pgsql_secondary')->transaction(function () use ($stockRowId) {
            DB::connection('pgsql_secondary')->table('product_stock')->where('id', $stockRowId)->lockForUpdate()->first();
        });
    } catch (QueryException) {
        $blocked = true;
    }

    expect($blocked)->toBeTrue();

    // Connection A commits, releasing the lock.
    DB::connection('pgsql')->commit();

    // Connection B's retry should now succeed immediately.
    $succeeded = false;
    DB::connection('pgsql_secondary')->transaction(function () use ($stockRowId, &$succeeded) {
        DB::connection('pgsql_secondary')->table('product_stock')->where('id', $stockRowId)->lockForUpdate()->first();
        $succeeded = true;
    });

    expect($succeeded)->toBeTrue();
});

it('rejects a raw bypass of StockService via the Postgres CHECK constraint, not just the app layer', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    app(StockService::class)->recordMovement($product, $warehouse, 10, StockMovementType::Received);

    $stockRowId = DB::connection('pgsql')->table('product_stock')
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->value('id');

    expect(fn () => DB::connection('pgsql')->table('product_stock')
        ->where('id', $stockRowId)
        ->update(['quantity' => DB::raw('quantity - 999')]))
        ->toThrow(QueryException::class);
});
