<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'product_name' => fake()->word(),
            'variant_name' => null,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 100, 5000),
            'line_total' => 0,
        ];
    }

    /**
     * `orders` has no `id` column — the primary key is the prefixed `_id`.
     * `getKey()` reads it directly rather than relying on the `id` alias
     * accessor, which is `null` for an unsaved model.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->getKey(),
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->getKey(),
            'product_name' => $product->name,
        ]);
    }
}
