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
        Schema::table('product_stock', function (Blueprint $table) {
            $table->unsignedInteger('reorder_point')->default(0)->after('quantity');
        });

        // Backfill: every existing warehouse row inherits the product's old
        // global value, so nothing looks in/out of stock differently the
        // moment this migration runs.
        DB::table('products')->select('id', 'reorder_point')->orderBy('id')->chunk(200, function ($products) {
            foreach ($products as $product) {
                DB::table('product_stock')
                    ->where('product_id', $product->id)
                    ->update(['reorder_point' => $product->reorder_point]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reorder_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reorder_point')->default(0)->after('selling_price');
        });

        // Lossy by nature (multiple per-warehouse values collapse into one) —
        // take the highest so nothing that was flagged low-stock anywhere
        // silently stops being flagged after a rollback.
        DB::table('product_stock')
            ->selectRaw('product_id, MAX(reorder_point) as reorder_point')
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('products')
                        ->where('id', $row->product_id)
                        ->update(['reorder_point' => $row->reorder_point]);
                }
            });

        Schema::table('product_stock', function (Blueprint $table) {
            $table->dropColumn('reorder_point');
        });
    }
};
