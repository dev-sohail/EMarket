<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * The types the `attributes.type` enum accepts.
     *
     * @var array<int, string>
     */
    public const TYPES = ['text', 'number', 'select', 'multiselect', 'color'];

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            // `attributes.slug` carries a unique index, so the generated value
            // has to be unique as well or bulk creates collide.
            'slug' => fake()->unique()->slug(),
            'type' => fake()->randomElement(self::TYPES),
            'is_filterable' => fake()->boolean(),
            'is_variant' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * An attribute of this exact type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }

    /**
     * An attribute that can be used as a storefront filter.
     *
     * Only the flag is set. Forcing a type here meant `ofType(Text)->filterable()`
     * quietly produced a select, which hid the type filter in the shop sidebar.
     */
    public function filterable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }
}
