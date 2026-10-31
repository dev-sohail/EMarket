<?php

namespace Tests\Feature;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards for the product create/edit forms and the image manager
 * (resources/views/admin/product/{create,edit,images}.blade.php).
 *
 * Defects these screens shipped with:
 *
 *  1. The bulk-delete form posted `image_ids` as a JSON string into a single
 *     hidden input, while the endpoint validates `image_ids` as an array, so
 *     "Delete Selected" always answered 422 and deleted nothing.
 *  2. The edit form round-tripped a variant's identifier through
 *     `$variant['id']`, but `ProductVariant::toArray()` never emits `id` (the
 *     alias is an accessor, not an appended attribute). Every existing row was
 *     therefore submitted with an empty id and the service tried to insert it
 *     as a new variant, which collides on `combination_key` and fails the whole
 *     save.
 *  3. For the same reason the variant attribute-value checkboxes never came back
 *     checked, so saving a product silently stripped the attribute combination
 *     from each variant.
 *  4. A dynamically added variant row could not be removed: the remove button
 *     was a sibling of the `data-removable` wrapper rather than a descendant,
 *     and the next row reused `children.length` as its index, so removing a row
 *     and adding another produced two rows sharing one array key.
 *  5. The form offered a "Low Stock Alert" field that has no column on
 *     `products` and no validation rule, so the value was discarded on submit.
 */
class AdminProductFormTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'product-form-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['products.view', 'products.create', 'products.update', 'products.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'products',
            ])->getKey());
        }

        return $admin;
    }

    protected function createPage(User $actor): string
    {
        return $this->actingAs($actor, 'admin')
            ->get(route('admin.products.create'))
            ->assertOk()
            ->getContent();
    }

    protected function editPage(User $actor, Product $product): string
    {
        return $this->actingAs($actor, 'admin')
            ->get(route('admin.products.edit', $product->getKey()))
            ->assertOk()
            ->getContent();
    }

    /**
     * A colour attribute with two values, marked as used on variants.
     */
    protected function variantAttribute(): Attribute
    {
        $attribute = Attribute::create([
            'name' => 'Colour',
            'slug' => 'colour',
            'type' => AttributeType::Color->value,
            'is_variant' => true,
        ]);

        foreach (['Red', 'Blue'] as $value) {
            AttributeValue::create([
                'attribute_id' => $attribute->getKey(),
                'value' => $value,
            ]);
        }

        return $attribute->fresh('values');
    }

    // -----------------------------------------------------------------------
    // Image manager
    // -----------------------------------------------------------------------

    public function test_the_image_manager_form_uses_array_field_names(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        ProductImage::create(['product_id' => $product->getKey(), 'path' => 'one.png']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.products.images', $product->getKey()))
            ->assertOk()
            ->getContent();

        // The endpoint validates `image_ids` as an array. A single hidden input
        // named `image_ids` can only ever carry a string, which is a 422.
        $this->assertMatchesRegularExpression('/name="image_ids\[\]"/', $html);
    }

    public function test_bulk_delete_removes_the_selected_images(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $product = Product::factory()->create();

        $keep = ProductImage::create(['product_id' => $product->getKey(), 'path' => 'keep.png', 'is_primary' => true]);
        $dropOne = ProductImage::create(['product_id' => $product->getKey(), 'path' => 'drop-one.png']);
        $dropTwo = ProductImage::create(['product_id' => $product->getKey(), 'path' => 'drop-two.png']);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.products.images.bulk-delete', $product->getKey()), [
                'image_ids' => [$dropOne->getKey(), $dropTwo->getKey()],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_images', ['_id' => $keep->getKey()]);
        $this->assertDatabaseMissing('product_images', ['_id' => $dropOne->getKey()]);
        $this->assertDatabaseMissing('product_images', ['_id' => $dropTwo->getKey()]);
    }

    // -----------------------------------------------------------------------
    // Variant round-trip
    // -----------------------------------------------------------------------

    public function test_the_edit_form_posts_the_existing_variant_identifier(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Hoodie']);
        $variant = ProductVariant::create([
            'product_id' => $product->getKey(),
            'sku' => 'HOODIE-S',
            'name' => 'Small',
            'price' => 40,
            'stock_quantity' => 3,
            'combination_key' => 'Small',
        ]);

        $html = $this->editPage($admin, $product);

        // `toArray()` has no `id`; only `_id` is emitted. Without the key the
        // row is submitted as a new variant and collides on combination_key.
        $this->assertStringContainsString(
            'name="variants[0][id]" value="'.$variant->getKey().'"',
            $html
        );
    }

    public function test_saving_an_existing_variant_updates_it_instead_of_inserting(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Hoodie', 'sku' => 'HOODIE-1']);
        $variant = ProductVariant::create([
            'product_id' => $product->getKey(),
            'sku' => 'HOODIE-S',
            'name' => 'Small',
            'price' => 40,
            'stock_quantity' => 3,
            'combination_key' => 'Small',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.products.update', $product->getKey()), [
                'name' => 'Hoodie',
                'sku' => 'HOODIE-1',
                'base_price' => 60,
                'variants' => [
                    ['id' => $variant->getKey(), 'name' => 'Small', 'sku' => 'HOODIE-S', 'price' => 45, 'stock' => 5],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('45.00', $variant->fresh()->price);
    }

    public function test_existing_variant_attribute_values_come_back_checked(): void
    {
        $admin = $this->admin();
        $attribute = $this->variantAttribute();
        $product = Product::factory()->create(['name' => 'Hoodie']);

        $variant = ProductVariant::create([
            'product_id' => $product->getKey(),
            'sku' => 'HOODIE-R',
            'name' => 'Red',
            'price' => 40,
            'stock_quantity' => 3,
            'combination_key' => 'Red',
        ]);

        $red = $attribute->values->firstWhere('value', 'Red');
        $variant->attributeValues()->attach($red->getKey(), ['attribute_id' => $attribute->getKey()]);

        $html = $this->editPage($admin, $product);

        $this->assertMatchesRegularExpression(
            '/name="variants\[0\]\[attribute_values\]\[\]" value="'.preg_quote($red->getKey(), '/').'"[^>]*checked/',
            $html
        );
    }

    // -----------------------------------------------------------------------
    // Form markup
    // -----------------------------------------------------------------------

    public function test_the_new_variant_template_only_offers_variant_attributes(): void
    {
        $admin = $this->admin();
        $this->variantAttribute();

        $nonVariant = Attribute::create([
            'name' => 'Care Instructions',
            'slug' => 'care-instructions',
            'type' => AttributeType::Text->value,
            'is_variant' => false,
        ]);

        AttributeValue::create(['attribute_id' => $nonVariant->getKey(), 'value' => 'Machine wash']);

        $html = $this->createPage($admin);

        // It still belongs in the Attributes card, so the check is scoped to
        // the variant picker. A non-variant attribute there produces a row
        // whose validation always fails, because a variant may only reference
        // an attribute the product itself declares.
        $this->assertStringContainsString('Care Instructions', $html);

        preg_match('/<template id="variant-row-template">(.*?)<\/template>/s', $html, $template);

        $this->assertNotEmpty($template, 'the variant row template is missing');
        $this->assertStringNotContainsString('Care Instructions', $template[1]);
        $this->assertStringContainsString('Colour', $template[1]);
    }

    public function test_a_dynamically_added_variant_row_can_be_removed(): void
    {
        $admin = $this->admin();
        $html = $this->createPage($admin);

        // The remove button must sit inside the row it removes, otherwise
        // `closest('[data-removable]')` finds nothing and the click is a no-op.
        $this->assertMatchesRegularExpression(
            '/data-removable[^>]*>.*?data-action="removeVariant"/s',
            $html
        );
    }

    public function test_the_form_does_not_offer_a_field_with_no_column(): void
    {
        $admin = $this->admin();

        $html = $this->createPage($admin);

        // `products` has no `low_stock_threshold` column and no validation rule,
        // so the value was accepted by the browser and thrown away on submit.
        $this->assertStringNotContainsString('name="low_stock_threshold"', $html);
    }

    public function test_organisation_selects_prefill_from_the_saved_product(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Kettle']);
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $tag = Tag::create(['name' => 'Clearance', 'slug' => 'clearance']);

        $product->categories()->attach($category);
        $product->brands()->attach($brand);
        $product->tags()->attach($tag);

        $html = $this->editPage($admin, $product);

        foreach ([$category, $brand, $tag] as $row) {
            $this->assertMatchesRegularExpression(
                '/value="'.preg_quote($row->getKey(), '/').'" selected/',
                $html,
                'relation option was not preselected'
            );
        }
    }

    public function test_the_create_form_renders_with_no_products_at_all(): void
    {
        $this->createPage($this->admin());
    }

    public function test_the_edit_form_reports_a_validation_failure_on_the_field(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        // Follow the redirect on the existing session: re-authenticating would start
        // a fresh one and drop the flashed error bag. Reading the bag with
        // assertSessionHasErrors() would consume it, so this asserts on the
        // rendered page instead, which is what the operator actually sees.
        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.products.edit', $product->getKey()))
            ->put(route('admin.products.update', $product->getKey()), [
                'name' => '',
                'base_price' => -5,
            ])
            ->assertRedirect(route('admin.products.edit', $product->getKey()));

        $html = $this->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Product name is required.', $html);
        $this->assertStringContainsString('must be at least 0', $html);
    }

    public function test_the_image_manager_reports_an_empty_state(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.products.images', $product->getKey()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No images', $html);
    }

    public function test_uploading_through_the_image_manager_stores_the_file(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $product = Product::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.products.images.store', $product->getKey()), [
                'images' => [UploadedFile::fake()->image('product.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $product->images()->count());
    }
}
