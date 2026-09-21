<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(4, 1, 500);

        return [
            'category_id' => null,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'barcode' => fake()->optional()->ean13(),
            'unit_of_measure' => 'pcs',
            'cost_price' => $cost,
            'selling_price' => $cost * fake()->randomFloat(2, 1.2, 2.5),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withCategory(): static
    {
        return $this->state(fn () => ['category_id' => ProductCategory::factory()]);
    }
}
