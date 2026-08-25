<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Nursery Supply',
            'contact_email' => fake()->companyEmail(),
            'lead_time_days' => fake()->numberBetween(2, 21),
        ];
    }
}
