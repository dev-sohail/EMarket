<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->slug(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'base_price' => fake()->randomFloat(2, 100, 5000),
            'cost_price' => fake()->randomFloat(2, 50, 3000),
            'sale_price' => null,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'stock_status' => 'in_stock',
            'stock_quantity' => fake()->numberBetween(10, 500),
            'weight' => fake()->randomFloat(2, 0.1, 20),
            'length' => fake()->randomFloat(2, 1, 50),
            'width' => fake()->randomFloat(2, 1, 50),
            'height' => fake()->randomFloat(2, 1, 50),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'is_new' => fake()->boolean(10),
            'return_allowed' => true,
            'meta_title' => fake()->sentence(),
            'meta_description' => fake()->sentence(),
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function noReturn(): static
    {
        return $this->state(fn (array $attributes) => [
            'return_allowed' => false,
        ]);
    }
}
