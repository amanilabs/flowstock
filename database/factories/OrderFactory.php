<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Note: this factory builds a raw `orders` row only. It does NOT run
 * OrderService, so a confirmed()/shipped() order made this way has no
 * matching stock_reservations or stock_movements rows unless a test
 * creates them explicitly.
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_number' => 'ORD-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => OrderStatus::Pending,
            'total_amount' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Shipped,
            'confirmed_at' => now(),
            'shipped_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
