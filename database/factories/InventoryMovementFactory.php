<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['in', 'out', 'out', 'out']);

        return [
            'product_id' => Product::factory(),
            'type' => $type,
            'quantity' => $type === 'in'
                ? fake()->numberBetween(50, 300)
                : fake()->numberBetween(1, 25),
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'source' => fake()->randomElement(['manual', 'supplier_feed', 'sale']),
        ];
    }
}
