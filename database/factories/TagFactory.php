<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(2, true)),
            'slug' => '',
        ];
    }

    /**
     * Keep the slug derived from whatever name the model ends up with.
     *
     * Setting the slug in definition() alone is not enough: the definition is
     * evaluated first and any `name` passed to create() is applied over the top,
     * so `factory()->create(['name' => 'Merino Wool'])` would keep the slug
     * generated for the factory's own random name. Recomputing in afterMaking
     * runs after those overrides, which restores the invariant the admin form
     * enforces and the unique index depends on.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Tag $tag): void {
            $tag->slug = Str::slug((string) $tag->name);
        });
    }

    /**
     * A tag with this exact name.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => ['name' => $name]);
    }
}
