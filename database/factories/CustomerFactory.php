<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            // A nested factory, not `User::factory()->create()`: the eager call
            // wrote a user row every time the definition was resolved — including
            // when the caller immediately overrode `user_id` with their own user,
            // leaving an orphan behind on every one of those calls. The nested
            // factory is resolved when the customer is actually created and
            // yields to an override.
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
