<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // Variant-scoped rows are the exception, not the default, so the
            // common case leaves this null and matches the single unique index
            // on (product_id, variant_id).
            'variant_id' => null,
            'quantity' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
            'reorder_level' => 5,
            'max_stock' => 1000,
        ];
    }

    /**
     * Stock held for one variant of the product.
     */
    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => $variant->getKey(),
        ]);
    }

    /**
     * A row with exactly this quantity on hand.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => ['quantity' => $quantity]);
    }
}
