<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReservation>
 */
class StockReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->numberBetween(1, 20),
            'status' => ReservationStatus::Active,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Released]);
    }

    public function fulfilled(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Fulfilled]);
    }
}
