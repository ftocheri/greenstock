<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    private const PLANTS = [
        'Heirloom Tomato', 'Bell Pepper', 'Basil', 'Lavender', 'Sunflower',
        'Marigold', 'Cucumber', 'Zucchini', 'Rosemary', 'Kale', 'Spinach',
        'Snap Pea', 'Carrot', 'Radish', 'Cilantro', 'Mint', 'Zinnia',
        'Cosmos', 'Pumpkin', 'Watermelon',
    ];

    private const FORMATS = [
        '%s Seed Packet', '%s Starter Plant', 'Organic %s Seeds',
        '%s Seedling Tray', '%s Bulb Pack',
    ];

    public function definition(): array
    {
        $plant = fake()->randomElement(self::PLANTS);
        $format = fake()->randomElement(self::FORMATS);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => sprintf($format, $plant),
            'category_id' => Category::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_price' => fake()->randomFloat(2, 1.99, 49.99),
            'reorder_threshold' => fake()->numberBetween(5, 30),
            'description' => fake()->sentence(12),
        ];
    }
}
