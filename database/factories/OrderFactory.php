<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => fake()->unique()->bothify('ORD-####-######'),
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => fake()->randomFloat(2, 100, 5000),
            'discount' => 0,
            'shipping_cost' => fake()->randomFloat(2, 50, 500),
            'tax' => fake()->randomFloat(2, 10, 200),
            'additional_charges' => 0,
            'grand_total' => fake()->randomFloat(2, 200, 6000),
        ];
    }
}
