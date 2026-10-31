<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'session_id' => null,
        ];
    }

    /**
     * `customers` has no `id` column — the primary key is the prefixed `_id`.
     * `getKey()` reads it directly rather than relying on the `id` alias
     * accessor, which is `null` for an unsaved model.
     */
    public function forCustomer(?Customer $customer = null): static
    {
        $customer ??= Customer::factory()->create();

        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->getKey(),
            'session_id' => null,
        ]);
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => null,
            'session_id' => $sessionId,
        ]);
    }
}
