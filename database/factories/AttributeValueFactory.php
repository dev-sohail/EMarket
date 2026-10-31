<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * Only the columns the live `attribute_values` table actually has.
     *
     * `AttributeValue` lists `color_code` and `sort_order` as fillable, but
     * neither exists in the live schema — they are left over from a migration
     * that was never applied. Setting them here would fail on insert, so the
     * factory sticks to `value` until the table catches up with the model.
     */
    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'value' => fake()->unique()->word(),
        ];
    }

    /**
     * A value belonging to this attribute, instead of a new one.
     */
    public function forAttribute(Attribute $attribute): static
    {
        return $this->state(fn (array $attributes) => [
            'attribute_id' => $attribute->getKey(),
        ]);
    }

    public function withText(string $value): static
    {
        return $this->state(fn (array $attributes) => ['value' => $value]);
    }
}
