<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The back-office product form is the only writer of products, and it is
 * routinely submitted as a partial update (the toggle endpoints, the image
 * manager and the variant rows each post their own slice). ProductService
 * therefore has to tell three cases apart for every relation it owns:
 *
 *  - the key is absent  -> the caller did not mention the relation, so it must
 *                          be left exactly as it was;
 *  - the key is []      -> the caller asked for the relation to be emptied;
 *  - the key is a list  -> the caller asked for exactly that list.
 *
 * Collapse the first two together and every partial update silently deletes a
 * product's whole variant catalogue, which is what the request layer used to do
 * by materialising an empty list for every key it did not receive.
 */
class ProductPartialUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_update_that_omits_a_collection_leaves_it_untouched(): void
    {
        $admin = $this->adminWithProductPermission();

        $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes-'.uniqid(), 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Running Shoe', 'is_active' => true, 'is_featured' => true]);
        $product->categories()->attach($category->getKey());
        $variant = $this->variantFor($product, 'RUN-SHOE-1');

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', $product), ['name' => 'Running Shoe Mk II']);

        $response->assertRedirect(route('admin.products.edit', $product));
        $response->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('Running Shoe Mk II', $product->name);
        $this->assertTrue($product->is_active, 'An omitted toggle must not be written as false.');
        $this->assertTrue($product->is_featured, 'An omitted toggle must not be written as false.');
        $this->assertSame(
            [$category->getKey()],
            $product->categories()->pluck('categories._id')->all(),
            'A payload without category_ids must not empty the category list.'
        );
        $this->assertSame(
            1,
            $product->variants()->count(),
            'A payload without variants must not delete the variant catalogue.'
        );
        $this->assertDatabaseHas('product_variants', ['_id' => $variant->getKey()]);
    }

    public function test_an_explicit_empty_variant_list_removes_the_variants(): void
    {
        $admin = $this->adminWithProductPermission();

        $product = Product::factory()->create();
        $this->variantFor($product, 'RUN-SHOE-1');
        $this->variantFor($product, 'RUN-SHOE-2');

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', $product), ['variants' => []]);

        $response->assertRedirect(route('admin.products.edit', $product));
        $response->assertSessionHasNoErrors();

        $this->assertSame(0, $product->variants()->count());
        $this->assertTrue(
            $product->inventory()->whereNull('variant_id')->exists(),
            'A product left without variants falls back to base stock.'
        );
    }

    public function test_a_submitted_variant_of_another_product_is_rejected(): void
    {
        $admin = $this->adminWithProductPermission();

        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $foreignVariant = $this->variantFor($otherProduct, 'FOREIGN-1');

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'variants' => [[
                    'id' => $foreignVariant->getKey(),
                    'name' => 'Stolen',
                    'sku' => 'STOLEN-1',
                    'price' => 10,
                    'stock' => 1,
                ]],
            ]);

        $response->assertSessionHasErrors('variants.0.id');
        $this->assertDatabaseHas('product_variants', ['_id' => $foreignVariant->getKey()]);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'STOLEN-1']);
    }

    public function test_a_variant_cannot_be_described_with_an_undeclared_attribute_value(): void
    {
        $admin = $this->adminWithProductPermission();

        $product = Product::factory()->create();

        $declared = Attribute::create(['name' => 'Colour', 'slug' => 'colour-'.uniqid(), 'type' => 'select']);
        $undeclared = Attribute::create(['name' => 'Size', 'slug' => 'size-'.uniqid(), 'type' => 'select']);
        $declaredValue = AttributeValue::create([
            'attribute_id' => $declared->getKey(),
            'value' => 'Red',
        ]);
        $foreignValue = AttributeValue::create([
            'attribute_id' => $undeclared->getKey(),
            'value' => 'XL',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'attributes' => [[
                    'attribute_id' => $declared->getKey(),
                    'values' => [$declaredValue->getKey()],
                ]],
                'variants' => [[
                    'name' => 'Red',
                    'sku' => 'RED-1',
                    'price' => 10,
                    'stock' => 1,
                    'attribute_values' => [$foreignValue->getKey()],
                ]],
            ]);

        $response->assertSessionHasErrors('variants.0.attribute_values');
        $this->assertDatabaseMissing('product_variants', ['sku' => 'RED-1']);
    }

    public function test_the_product_form_offers_the_attribute_keys_that_exist(): void
    {
        // `attributes` and `attribute_values` have no `id` column; the key is the
        // prefixed `_id`. The form used to read `->id`, which is null, so it
        // rendered empty attribute_id/value inputs and every save then failed
        // `exists:attributes,_id`. Asserting on the rendered key catches that
        // without needing a full product save.
        $admin = $this->adminWithProductPermission();

        $attribute = Attribute::create([
            'name' => 'Colour',
            'slug' => 'colour-'.uniqid(),
            'type' => 'multiselect',
        ]);
        $value = AttributeValue::factory()->forAttribute($attribute)->withText('Red')->create();

        foreach (['admin.products.create' => null, 'admin.products.edit' => $this->productWithAttribute($attribute, $value)] as $route => $product) {
            $url = $product
                ? route($route, $product)
                : route($route);

            $html = $this->actingAs($admin, 'admin')->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(
                'name="attributes[0][attribute_id]" value="'.$attribute->getKey().'"',
                $html,
                $route.' must submit the attribute\'s real primary key.'
            );
            $this->assertStringContainsString(
                'value="'.$value->getKey().'"',
                $html,
                $route.' must submit each value\'s real primary key.'
            );
        }
    }

    public function test_the_product_form_submits_an_attribute_that_can_pass_validation(): void
    {
        // The end-to-end guarantee: what the form renders has to be what
        // StoreProductRequest accepts, or attributes cannot be attached at all.
        $admin = $this->adminWithProductPermission();

        $attribute = Attribute::create([
            'name' => 'Colour',
            'slug' => 'colour-'.uniqid(),
            'type' => 'select',
        ]);
        $value = AttributeValue::factory()->forAttribute($attribute)->withText('Red')->create();
        $product = $this->productWithAttribute($attribute, $value);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', $product), [
                'attributes' => [
                    ['attribute_id' => $attribute->getKey(), 'values' => [$value->getKey()]],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    private function productWithAttribute(Attribute $attribute, AttributeValue $value): Product
    {
        $product = Product::factory()->create(['is_active' => true]);
        $product->attributeValues()->attach($value->getKey(), ['attribute_id' => $attribute->getKey()]);

        return $product;
    }

    private function variantFor(Product $product, string $sku): ProductVariant
    {
        return $product->variants()->create([
            'sku' => $sku,
            'name' => $sku,
            'price' => 99.99,
            'stock_quantity' => 10,
            'is_active' => true,
            'combination_key' => strtolower($sku),
        ]);
    }

    /**
     * AdminMiddleware requires an active account on the admin guard, at least
     * one role, and the permission mapped to the route in config/admin.php.
     */
    private function adminWithProductPermission(): User
    {
        $role = Role::create([
            'name' => 'Catalog Editor',
            'slug' => 'catalog-editor-'.uniqid(),
        ]);

        // products.view covers the form pages, products.update the saves.
        foreach (['products.view', 'products.update'] as $name) {
            $permission = Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name).'-'.uniqid(),
                'group_name' => 'products',
            ]);
            $role->permissions()->attach($permission->getKey());
        }

        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($role->getKey());

        return $admin;
    }
}
