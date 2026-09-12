<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            $table->integer('quantity')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['tenant_id', 'warehouse_id']);
        });

        // Postgres-only: SQLite (used in tests) can't add constraints after
        // table creation. Enforcement in real environments relies on this;
        // the application layer (StockService) enforces it everywhere.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE product_stock ADD CONSTRAINT product_stock_quantity_non_negative CHECK (quantity >= 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock');
    }
};
