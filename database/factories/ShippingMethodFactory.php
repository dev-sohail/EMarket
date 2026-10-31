<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Standard Shipping', 'Express Shipping', 'Same Day Delivery']),
            'description' => fake()->sentence(),
            'base_cost' => fake()->randomFloat(2, 100, 500),
            'per_kg_cost' => fake()->randomFloat(2, 10, 100),
            'estimated_days' => fake()->numberBetween(1, 7),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
