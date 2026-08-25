<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'status' => fake()->randomElement(['pending', 'fulfilled', 'fulfilled', 'cancelled']),
            'placed_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
