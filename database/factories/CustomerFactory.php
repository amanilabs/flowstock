<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'billing_address_line1' => fake()->streetAddress(),
            'billing_address_line2' => fake()->optional()->secondaryAddress(),
            'billing_city' => fake()->city(),
            'billing_state' => fake()->optional()->state(),
            'billing_postal_code' => fake()->postcode(),
            'billing_country' => fake()->country(),
            'shipping_address_line1' => fake()->streetAddress(),
            'shipping_address_line2' => fake()->optional()->secondaryAddress(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->optional()->state(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => fake()->country(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
