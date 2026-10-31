<?php

namespace Tests\Feature;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Attributes as storefront filters.
 *
 * The `is_filterable` flag existed on the model, in validation and in the admin
 * form, but nothing ever read it: the shop sidebar offered categories, brands
 * and price only, and `ProductService::search` had no attribute handling at all.
 * These tests pin the behaviour that was missing.
 */
class AttributeFilteringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sign in as an admin allowed to manage attributes.
     *
     * The attribute routes are mapped to explicit permissions in
     * config/admin.php, so without this every write comes back 403 and the
     * assertions about what was stored pass vacuously.
     */
    protected function actingAsAttributeManager(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $role = Role::create([
            'name' => 'Attribute Manager',
            'slug' => 'attribute-manager-filtering',
        ]);

        foreach (['attributes.create', 'attributes.update', 'attributes.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name).'-filtering',
                'group_name' => 'attributes',
            ])->getKey());
        }

        $admin->assignRole('Attribute Manager');

        $this->actingAs($admin, 'admin');
    }

    /**
     * Attach a value to a product through the pivot, which also carries a
     * denormalised attribute_id.
     */
    protected function attach(Product $product, AttributeValue $value): void
    {
        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_id' => $value->attribute_id,
            'attribute_value_id' => $value->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeProduct(string $name): Product
    {
        return Product::factory()->create(['name' => $name]);
    }

    // ------------------------------------------------------------------ query

    public function test_the_search_narrows_by_a_selected_attribute_value(): void
    {
        $material = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Material', 'slug' => 'material',
        ]);
        $wool = AttributeValue::factory()->forAttribute($material)->withText('Wool')->create();

        $match = $this->makeProduct('Wool Coat');
        $other = $this->makeProduct('Cotton Shirt');

        $this->attach($match, $wool);
        $this->attach($other, AttributeValue::factory()->forAttribute($material)->withText('Cotton')->create());

        $results = app(ProductService::class)->search([
            'attribute_value_ids' => ['material' => [$wool->getKey()]],
        ]);

        $this->assertSame([$match->getKey()], $results->pluck('_id')->all());
    }

    public function test_selecting_several_values_of_one_attribute_widens_the_result(): void
    {
        // Red OR Blue must return both, not require a product to be both — that
        // is the whole point of offering more than one checkbox.
        $colour = Attribute::factory()->ofType(AttributeType::Color->value)->filterable()->create([
            'name' => 'Colour', 'slug' => 'colour',
        ]);
        $red = AttributeValue::factory()->forAttribute($colour)->withText('Red')->create();
        $blue = AttributeValue::factory()->forAttribute($colour)->withText('Blue')->create();

        $redProduct = $this->makeProduct('Red Dress');
        $blueProduct = $this->makeProduct('Blue Dress');
        $greenProduct = $this->makeProduct('Green Dress');

        $this->attach($redProduct, $red);
        $this->attach($blueProduct, $blue);
        $this->attach($greenProduct, AttributeValue::factory()->forAttribute($colour)->withText('Green')->create());

        $results = app(ProductService::class)->search([
            'attribute_value_ids' => ['colour' => [$red->getKey(), $blue->getKey()]],
        ]);

        $this->assertEqualsCanonicalizing(
            [$redProduct->getKey(), $blueProduct->getKey()],
            $results->pluck('_id')->all(),
        );
    }

    public function test_two_attributes_narrow_together(): void
    {
        $material = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Material', 'slug' => 'material',
        ]);
        $colour = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Colour', 'slug' => 'colour',
        ]);

        $wool = AttributeValue::factory()->forAttribute($material)->withText('Wool')->create();
        $red = AttributeValue::factory()->forAttribute($colour)->withText('Red')->create();

        $both = $this->makeProduct('Red Wool Coat');
        $onlyWool = $this->makeProduct('Blue Wool Coat');
        $onlyRed = $this->makeProduct('Red Cotton Shirt');

        $this->attach($both, $wool);
        $this->attach($both, $red);
        $this->attach($onlyWool, $wool);
        $this->attach($onlyRed, $red);

        $results = app(ProductService::class)->search([
            'attribute_value_ids' => [
                'material' => [$wool->getKey()],
                'colour' => [$red->getKey()],
            ],
        ]);

        $this->assertSame([$both->getKey()], $results->pluck('_id')->all());
    }

    public function test_an_empty_attribute_selection_does_not_blank_the_catalogue(): void
    {
        $material = Attribute::factory()->filterable()->create(['slug' => 'material']);
        $wool = AttributeValue::factory()->forAttribute($material)->withText('Wool')->create();
        $product = $this->makeProduct('Wool Coat');
        $this->attach($product, $wool);

        $results = app(ProductService::class)->search([
            'attribute_value_ids' => ['material' => []],
        ]);

        $this->assertTrue($results->total() >= 1);
    }

    // -------------------------------------------------------------- controller

    public function test_the_shop_page_offers_only_filterable_attributes(): void
    {
        $filterable = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Material', 'slug' => 'material',
        ]);
        AttributeValue::factory()->forAttribute($filterable)->withText('Wool')->create();

        Attribute::factory()->ofType(AttributeType::Select->value)->create([
            'name' => 'Internal Only', 'slug' => 'internal-only',
            'is_filterable' => false,
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Material')
            ->assertSee('Wool')
            ->assertDontSee('Internal Only');
    }

    public function test_a_text_attribute_is_not_offered_as_a_checkbox_group(): void
    {
        // A free-text attribute has no fixed option set, so it cannot be a filter.
        $note = Attribute::factory()->ofType(AttributeType::Text->value)->filterable()->create([
            'name' => 'Care Note', 'slug' => 'care-note',
        ]);
        AttributeValue::factory()->forAttribute($note)->withText('Dry clean')->create();

        $this->get('/shop')->assertOk()->assertDontSee('Care Note');
    }

    public function test_the_shop_page_filters_by_attribute(): void
    {
        $material = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Material', 'slug' => 'material',
        ]);
        $wool = AttributeValue::factory()->forAttribute($material)->withText('Wool')->create();

        $match = $this->makeProduct('Wool Coat');
        $this->attach($match, $wool);
        $this->attach(
            $this->makeProduct('Cotton Shirt'),
            AttributeValue::factory()->forAttribute($material)->withText('Cotton')->create(),
        );

        $this->get('/shop?attributes[material][]='.$wool->getKey())
            ->assertOk()
            ->assertSee('Wool Coat')
            ->assertDontSee('Cotton Shirt');
    }

    public function test_a_non_filterable_attribute_in_the_url_is_ignored(): void
    {
        // The parameter is user-supplied, so without the filterable check a
        // hand-edited link could filter on an attribute that was never exposed.
        $hidden = Attribute::factory()->ofType(AttributeType::Select->value)->create([
            'name' => 'Internal Only', 'slug' => 'internal-only', 'is_filterable' => false,
        ]);
        $secret = AttributeValue::factory()->forAttribute($hidden)->withText('Secret')->create();

        $excluded = $this->makeProduct('Cotton Shirt');
        $this->attach($excluded, $secret);

        $this->get('/shop?attributes[internal-only][]='.$secret->getKey())
            ->assertOk()
            ->assertSee('Cotton Shirt');
    }

    public function test_a_value_id_from_another_attribute_cannot_widen_the_match(): void
    {
        // The guard belongs where the untrusted input arrives, so this goes
        // through the route rather than straight at the service.
        $material = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Material', 'slug' => 'material',
        ]);
        AttributeValue::factory()->forAttribute($material)->withText('Wool')->create();

        $colour = Attribute::factory()->ofType(AttributeType::Select->value)->filterable()->create([
            'name' => 'Colour', 'slug' => 'colour',
        ]);
        $red = AttributeValue::factory()->forAttribute($colour)->withText('Red')->create();

        $target = $this->makeProduct('Red Dress Shirt');
        $this->attach($target, $red);

        $this->get('/shop?attributes[material][]='.$red->getKey())
            ->assertOk()
            ->assertSee('Red Dress Shirt');
    }

    // ------------------------------------------------------------- ordering

    public function test_attribute_values_keep_the_order_the_admin_form_submitted(): void
    {
        // `attribute_values` had no sort_order column, so the order chosen in the
        // form was discarded and values could only come back in insertion order.
        $this->actingAsAttributeManager();

        $this->post('/admin/attributes', [
            'name' => 'Material',
            'slug' => 'material',
            'type' => AttributeType::Select->value,
            'values' => ['Zebra', 'Apple', 'Mango'],
        ])->assertSessionHasNoErrors();

        $created = Attribute::where('slug', 'material')->firstOrFail();

        $this->assertSame(
            ['Zebra', 'Apple', 'Mango'],
            $created->fresh()->values->pluck('value')->all(),
        );
    }

    public function test_reordering_existing_values_keeps_their_ids(): void
    {
        $this->actingAsAttributeManager();

        $attribute = Attribute::factory()->create(['slug' => 'material']);
        $zebra = AttributeValue::factory()->forAttribute($attribute)->withText('Zebra')->create();
        $apple = AttributeValue::factory()->forAttribute($attribute)->withText('Apple')->create();
        $product = Product::factory()->create();

        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $apple->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => AttributeType::Select->value,
            'values' => ['Apple', 'Zebra'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Apple', 'Zebra'], $attribute->fresh()->values->pluck('value')->all());

        // Reordering must not orphan the product link.
        $this->assertSame($apple->getKey(), $apple->fresh()->getKey());
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $apple->getKey(),
        ]);
        $this->assertNotNull($zebra->fresh());
    }
}
