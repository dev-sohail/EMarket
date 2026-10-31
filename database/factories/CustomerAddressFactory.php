<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        // `label`/`full_name` were never columns on `customer_addresses`; the
        // table stores first and last name separately and requires an email and
        // a two letter country. A factory that set the old names produced rows
        // that could not be inserted at all.
        return [
            'customer_id' => Customer::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'CH',
            'type' => 'shipping',
            'is_default' => fake()->boolean(30),
        ];
    }
}
