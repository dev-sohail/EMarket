<?php

namespace Database\Factories;

use App\Enums\PopupPosition;
use App\Enums\PopupSize;
use App\Enums\PopupType;
use App\Models\Popup;
use Illuminate\Database\Eloquent\Factories\Factory;

class PopupFactory extends Factory
{
    protected $model = Popup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'title' => $this->faker->sentence(),
            'subtitle' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'image' => null,
            'color' => $this->faker->hexColor,
            'overlay_color' => '#000000',
            'custom_html' => null,
            'url' => $this->faker->url(),
            'cta_text' => $this->faker->word,
            'position' => $this->faker->randomElement(PopupPosition::cases()),
            'size' => $this->faker->randomElement(PopupSize::cases()),
            'type' => $this->faker->randomElement(PopupType::cases()),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => $this->faker->boolean(80),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'show_close_button' => true,
            'enable_cookie' => true,
            'cookie_expiry_days' => 7,
            'delay_seconds' => 0,
            'show_on_mobile' => true,
            'show_on_desktop' => true,
            'show_only_once' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
