<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->numberBetween(0, 200),
            'reorder_point' => fake()->numberBetween(0, 50),
        ];
    }

    public function configure(): static
    {
        // Keep the "quantity == sum of movements" invariant true even for
        // factory-seeded fixture data, not just data created via StockService.
        return $this->afterCreating(function (ProductStock $stock) {
            if ($stock->quantity > 0) {
                StockMovement::create([
                    'tenant_id' => $stock->tenant_id,
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'type' => StockMovementType::Received,
                    'quantity_change' => $stock->quantity,
                    'note' => 'Factory-seeded initial stock',
                ]);
            }
        });
    }
}
