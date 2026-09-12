<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => fake()->randomElement(StockMovementType::cases()),
            'quantity_change' => fake()->numberBetween(1, 100),
            'note' => fake()->optional()->sentence(),
            'user_id' => null,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }

    public function withReference(Model $reference): static
    {
        return $this->state(fn () => [
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
        ]);
    }
}
