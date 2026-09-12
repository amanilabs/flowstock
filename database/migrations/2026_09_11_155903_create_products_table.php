<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('product_categories')->nullOnDelete();

            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->string('unit_of_measure')->default('pcs');

            $table->decimal('cost_price', 12, 4);
            $table->decimal('selling_price', 12, 4);

            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
