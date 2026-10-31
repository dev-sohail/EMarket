<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_cod' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
