<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\AttributeService;
use Database\Factories\AttributeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin CRUD for product attributes.
 *
 * The thread running through these tests is that neither `attribute_values`
 * nor the two pivots that reference it declare a foreign key or a cascade, so
 * a delete has to clear them by hand. The cleanup therefore lives in
 * `deleting` hooks on Attribute and AttributeValue rather than in the admin
 * controller, so that a test, a seeder or a console command deleting the
 * model directly gets the same cleanup. `test_deleting_an_attribute_cleans_up_every_pivot_row`
 * pins that for the direct-model path; the rest cover the admin.
 */
class AttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'email' => 'attributes-admin@example.test',
            'is_active' => true,
        ]);

        // Every admin.* route is mapped to an explicit permission in
        // config/admin.php, so the role needs them granted or the first
        // request comes back 403.
        $role = Role::create([
            'name' => 'Attribute Manager',
            'slug' => 'attribute-manager',
        ]);

        foreach (['attributes.view', 'attributes.create', 'attributes.update', 'attributes.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'attributes',
            ])->getKey());
        }

        $admin->assignRole('Attribute Manager');

        $this->actingAs($admin, 'admin');
    }

    /**
     * Attach a value to a product the way the schema requires.
     *
     * The pivots carry a denormalised `attribute_id` alongside
     * `attribute_value_id`, so both have to be supplied. Inserting through the
     * relation alone leaves it NULL and the insert is refused.
     *
     * @return array{0: Product, 1: AttributeValue}
     */
    protected function attachValueToProduct(AttributeValue $value): array
    {
        $product = Product::factory()->create();

        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_id' => $value->attribute_id,
            'attribute_value_id' => $value->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$product, $value];
    }

    // ------------------------------------------------------------------ index

    public function test_the_index_lists_attributes_with_their_values(): void
    {
        $attribute = Attribute::factory()->create(['name' => 'Material', 'slug' => 'material']);
        AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();

        $this->get('/admin/attributes')
            ->assertOk()
            ->assertSee('Material')
            ->assertSee('Wool');
    }

    public function test_the_index_shows_an_empty_state(): void
    {
        $this->get('/admin/attributes')->assertOk();
    }

    public function test_the_index_lists_an_attributes_values_inline(): void
    {
        $attribute = Attribute::factory()->create(['name' => 'Material', 'slug' => 'material']);
        AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();
        AttributeValue::factory()->forAttribute($attribute)->withText('Cotton')->create();

        $this->get('/admin/attributes')
            ->assertOk()
            ->assertSee('Wool, Cotton');
    }

    public function test_the_index_asks_for_confirmation_before_deleting(): void
    {
        // Deleting an attribute also deletes its values and every product pivot
        // that referenced them, so it must not be one unguarded click.
        $attribute = Attribute::factory()->create();

        $this->get('/admin/attributes')
            ->assertOk()
            ->assertSee('data-confirm', false);
    }

    // ------------------------------------------------------------------ forms

    public function test_the_create_form_offers_a_value_input_and_the_add_button(): void
    {
        // Without a values[] input the form cannot satisfy the request's
        // `values` rule, so an attribute could not be created from the UI.
        $response = $this->get('/admin/attributes/create')->assertOk();

        $response->assertSee('name="values[]"', false);
        $response->assertSee('data-action="addValue"', false);
    }

    public function test_the_create_form_script_is_nonce_aware(): void
    {
        // The "+ Add Value" button works through the delegated listener in
        // resources/js/app.js, which is only reached if the inline script is
        // allowed by the CSP — so the nonce has to be on the tag.
        $html = $this->get('/admin/attributes/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<script[^>]*nonce="[^"]+"/',
            (string) $html,
            'The value-management script must carry a CSP nonce.',
        );
        $this->assertStringNotContainsString('onclick=', (string) $html);
    }

    public function test_the_edit_form_prefills_the_existing_values(): void
    {
        $attribute = Attribute::factory()->create(['name' => 'Material', 'slug' => 'material']);
        AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();
        AttributeValue::factory()->forAttribute($attribute)->withText('Cotton')->create();

        $this->get('/admin/attributes/'.$attribute->getKey().'/edit')
            ->assertOk()
            ->assertSee('name="values[]"', false)
            ->assertSee('value="Wool"', false)
            ->assertSee('value="Cotton"', false);
    }

    public function test_the_index_paginates(): void
    {
        Attribute::factory()->count(18)->create();

        $response = $this->get('/admin/attributes')->assertOk();

        // 15 per page, so the second page has to exist.
        $this->assertCount(15, $response->viewData('attributes'));
        $response->assertSee('Next');
    }

    // ------------------------------------------------------------------ write

    public function test_an_attribute_can_be_created_with_its_values(): void
    {
        $this->post('/admin/attributes', [
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Wool', 'Cotton'],
            'is_filterable' => 1,
        ])
            ->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHas('success');

        $attribute = Attribute::where('slug', 'material')->firstOrFail();

        $this->assertSame('Material', $attribute->name);
        $this->assertTrue((bool) $attribute->is_filterable);
        $this->assertSame(['Wool', 'Cotton'], $attribute->values()->pluck('value')->all());
    }

    public function test_creating_an_attribute_rejects_a_duplicate_slug(): void
    {
        Attribute::factory()->create(['slug' => 'material']);

        $this->post('/admin/attributes', [
            'name' => 'Fabric',
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Wool'],
        ])->assertSessionHasErrors('slug');

        $this->assertSame(1, Attribute::count());
    }

    public function test_creating_an_attribute_requires_at_least_one_value(): void
    {
        $this->post('/admin/attributes', [
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
            'values' => [],
        ])->assertSessionHasErrors('values');

        $this->assertSame(0, Attribute::count());
    }

    public function test_creating_an_attribute_rejects_an_unknown_type(): void
    {
        $this->post('/admin/attributes', [
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'rainbow',
            'values' => ['Wool'],
        ])->assertSessionHasErrors('type');
    }

    public function test_updating_an_attribute_without_changing_its_slug_is_not_a_conflict(): void
    {
        // `unique` ignores the record by primary key, and this schema has no
        // `id` column — the key is the prefixed `_id`. Ignoring by the wrong
        // column makes every save collide with itself.
        $attribute = Attribute::factory()->create(['name' => 'Material', 'slug' => 'material']);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Wool'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Material', $attribute->fresh()->name);
    }

    public function test_updating_onto_another_attributes_slug_is_rejected(): void
    {
        Attribute::factory()->create(['slug' => 'material']);
        $other = Attribute::factory()->create(['slug' => 'fabric']);

        $this->put('/admin/attributes/'.$other->getKey(), [
            'name' => 'Other',
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Wool'],
        ])->assertSessionHasErrors('slug');

        $this->assertSame('fabric', $other->fresh()->slug);
    }

    public function test_unchanged_values_keep_their_id_so_product_links_survive(): void
    {
        // Replacing the value list wholesale would mint new ids for values whose
        // text did not change, and every product_attribute_values row pointing
        // at the old id would be left pointing at nothing.
        $attribute = Attribute::factory()->create(['slug' => 'material']);
        $kept = AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();
        [$product] = $this->attachValueToProduct($kept);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Wool', 'Cotton'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            AttributeValue::where('_id', $kept->getKey())->exists(),
            'An unchanged value must keep its id.'
        );

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $kept->getKey(),
        ]);
    }

    public function test_removing_a_value_clears_the_products_that_used_it(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'material']);
        $removed = AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();
        [$product] = $this->attachValueToProduct($removed);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => 'select',
            'values' => ['Cotton'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('attribute_values', ['_id' => $removed->getKey()]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $removed->getKey(),
        ]);
    }

    // ----------------------------------------------------------------- delete

    public function test_deleting_an_attribute_removes_its_values_and_pivots(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'material']);
        $value = AttributeValue::factory()->forAttribute($attribute)->create();
        [$product] = $this->attachValueToProduct($value);

        $this->delete('/admin/attributes/'.$attribute->getKey())
            ->assertRedirect(route('admin.attributes.index'));

        $this->assertDatabaseMissing('attributes', ['_id' => $attribute->getKey()]);
        $this->assertDatabaseMissing('attribute_values', ['_id' => $value->getKey()]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_deleting_an_attribute_cleans_up_every_pivot_row(): void
    {
        // The direct-model path, not the admin controller. If the cleanup only
        // lived in AttributeService this would leave orphans behind, which is
        // how a value ends up pointing at an attribute that no longer exists.
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->forAttribute($attribute)->create();
        [$product] = $this->attachValueToProduct($value);

        $variant = $product->variants()->create([
            'sku' => 'MAT-001',
            'price' => 49.90,
            'stock_quantity' => 5,
        ]);
        DB::table('variant_attribute_values')->insert([
            'product_variant_id' => $variant->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attribute->delete();

        $this->assertDatabaseMissing('attributes', ['_id' => $attribute->getKey()]);
        $this->assertDatabaseMissing('attribute_values', [
            'attribute_id' => $attribute->getKey(),
        ]);
        $this->assertSame(0, DB::table('product_attribute_values')->count());
        $this->assertSame(0, DB::table('variant_attribute_values')->count());
    }

    public function test_deleting_a_single_value_clears_only_its_own_pivots(): void
    {
        $attribute = Attribute::factory()->create();
        $doomed = AttributeValue::factory()->forAttribute($attribute)->create();
        $kept = AttributeValue::factory()->forAttribute($attribute)->create();

        [$product] = $this->attachValueToProduct($doomed);
        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $kept->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $doomed->delete();

        $this->assertDatabaseMissing('attribute_values', ['_id' => $doomed->getKey()]);
        $this->assertDatabaseHas('attribute_values', ['_id' => $kept->getKey()]);
        $this->assertSame(
            1,
            DB::table('product_attribute_values')
                ->where('attribute_value_id', $kept->getKey())
                ->count()
        );
    }

    public function test_the_service_delete_and_the_model_delete_agree(): void
    {
        // Both paths must leave the same state, otherwise whether pivots survive
        // depends on which code happened to call the delete.
        $viaService = Attribute::factory()->create();
        AttributeValue::factory()->forAttribute($viaService)->create();
        [$product] = $this->attachValueToProduct(
            AttributeValue::where('attribute_id', $viaService->getKey())->firstOrFail()
        );

        app(AttributeService::class)->delete($viaService->getKey());

        $this->assertDatabaseMissing('attributes', ['_id' => $viaService->getKey()]);
        $this->assertSame(0, DB::table('attribute_values')->count());
        $this->assertSame(0, DB::table('product_attribute_values')->count());
        $this->assertNotNull($product->fresh());
    }

    // ------------------------------------------------- blank submission safety

    public function test_a_blank_value_row_does_not_wipe_the_existing_values(): void
    {
        // The edit form always renders at least one row, so a submit with the
        // row left empty arrives as an empty list once blanks are filtered out.
        // That must be reported as a missing value, never taken as an
        // instruction to delete every value and every product link behind it.
        $attribute = Attribute::factory()->create(['slug' => 'material', 'type' => 'select']);
        $value = AttributeValue::factory()->forAttribute($attribute)->withText('Cotton')->create();
        [$product] = $this->attachValueToProduct($value);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => 'select',
            'values' => [''],
        ])->assertSessionHasErrors('values');

        $this->assertDatabaseHas('attribute_values', ['_id' => $value->getKey()]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    public function test_several_blank_value_rows_do_not_wipe_the_existing_values(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'material', 'type' => 'select']);
        $value = AttributeValue::factory()->forAttribute($attribute)->withText('Cotton')->create();
        [$product] = $this->attachValueToProduct($value);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => 'select',
            'values' => ['  ', ''],
        ])->assertSessionHasErrors('values');

        $this->assertDatabaseHas('attribute_values', ['_id' => $value->getKey()]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    public function test_the_service_ignores_a_wholly_blank_submission(): void
    {
        // The request filters blanks out, but a caller reaching the service
        // directly still must not be able to wipe a value list by passing [''].
        $attribute = Attribute::factory()->create(['slug' => 'material', 'type' => 'select']);
        $value = AttributeValue::factory()->forAttribute($attribute)->withText('Cotton')->create();
        [$product] = $this->attachValueToProduct($value);

        app(AttributeService::class)->update($attribute->getKey(), [
            'name' => $attribute->name,
            'values' => ['', '  '],
        ]);

        $this->assertDatabaseHas('attribute_values', ['_id' => $value->getKey()]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    public function test_a_value_submitted_alongside_blanks_is_still_applied(): void
    {
        // The guard must only ignore a submission that is entirely blank; a real
        // value in the list still reconciles as before.
        $attribute = Attribute::factory()->create(['slug' => 'material', 'type' => 'select']);
        $doomed = AttributeValue::factory()->forAttribute($attribute)->withText('Wool')->create();

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'material',
            'type' => 'select',
            'values' => ['', 'Cotton'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('attribute_values', ['_id' => $doomed->getKey()]);
        $this->assertDatabaseHas('attribute_values', ['value' => 'Cotton']);
    }

    // ------------------------------------------------ free-typed attribute types

    public function test_a_text_attribute_can_be_created_without_a_value_list(): void
    {
        // `text` and `number` are typed per product, so requiring a value list
        // for them made both types impossible to create through the admin at all.
        $this->post('/admin/attributes', [
            'name' => 'Care Instructions',
            'slug' => 'care-instructions',
            'type' => 'text',
        ])->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attributes', [
            'slug' => 'care-instructions',
            'type' => 'text',
        ]);
        $this->assertSame(0, DB::table('attribute_values')->count());
    }

    public function test_a_number_attribute_can_be_created_without_a_value_list(): void
    {
        $this->post('/admin/attributes', [
            'name' => 'Warranty Months',
            'slug' => 'warranty-months',
            'type' => 'number',
        ])->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attributes', [
            'slug' => 'warranty-months',
            'type' => 'number',
        ]);
    }

    public function test_a_list_attribute_still_requires_a_value(): void
    {
        // The relaxation above is scoped to the free-typed types only: a select
        // with nothing to select from is still a mistake worth reporting.
        $this->post('/admin/attributes', [
            'name' => 'Colour',
            'slug' => 'colour',
            'type' => 'select',
        ])->assertSessionHasErrors('values');

        $this->assertDatabaseMissing('attributes', ['slug' => 'colour']);
    }

    public function test_the_variant_flag_is_stored_when_the_form_submits_it(): void
    {
        $this->post('/admin/attributes', [
            'name' => 'Size',
            'slug' => 'size',
            'type' => 'select',
            'values' => ['Small', 'Large'],
            'is_filterable' => '1',
            'is_variant' => '1',
        ])->assertRedirect(route('admin.attributes.index'))
            ->assertSessionHasNoErrors();

        $attribute = Attribute::where('slug', 'size')->firstOrFail();

        $this->assertTrue((bool) $attribute->is_variant);
        $this->assertTrue((bool) $attribute->is_filterable);
    }

    public function test_the_variant_flag_can_be_cleared_again(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'size', 'is_variant' => true]);

        $this->put('/admin/attributes/'.$attribute->getKey(), [
            'name' => $attribute->name,
            'slug' => 'size',
            'type' => 'select',
        ])->assertSessionHasNoErrors();

        // The checkbox is absent from the payload when unticked, so the write has
        // to fall back to the database default rather than keep the old flag.
        $this->assertFalse((bool) $attribute->fresh()->is_variant);
    }

    // --------------------------------------------------------------- factory

    public function test_the_factories_build_consistent_attributes(): void
    {
        $attribute = Attribute::factory()->filterable()->create([
            'name' => 'Material',
            'slug' => 'material',
        ]);

        // filterable() sets only the flag, so the type is whatever the factory
        // generated — it used to force 'select' and mask the type filter.
        $this->assertContains($attribute->type, AttributeFactory::TYPES);
        $this->assertTrue((bool) $attribute->is_filterable);

        AttributeValue::factory()->count(3)->forAttribute($attribute)->create();

        $this->assertCount(3, $attribute->values()->get());
        $this->assertCount(3, DB::table('attribute_values')->where('attribute_id', $attribute->getKey())->get());
    }

    public function test_bulk_attribute_creates_do_not_collide_on_slug(): void
    {
        // `attributes.slug` has a unique index, so the generated slugs have to
        // be unique too or a bulk create fails on the constraint.
        Attribute::factory()->count(10)->create();

        $this->assertSame(10, Attribute::count());
        $this->assertSame(10, Attribute::distinct()->count('slug'));
    }
}
