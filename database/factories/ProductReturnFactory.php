<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'return_number' => fake()->unique()->bothify('RET-####-######'),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'status' => 'requested',
            'description' => fake()->sentence(),
            'admin_note' => null,
        ];
    }
}
