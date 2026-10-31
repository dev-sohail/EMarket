<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin CRUD for product tags.
 *
 * The regression that matters most here is the slug collision. `tags.slug`
 * carries a unique index in the database while `tags.name` carries none, so a
 * form that validates only the name lets two different names through and the
 * insert then dies on the unique constraint — a 500, not a validation error.
 * `test_two_names_that_produce_the_same_slug_are_rejected_not_crashed` pins it.
 */
class TagManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'email' => 'tags-admin@example.test',
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // tags.* routes are mapped to tags.view/create/update/delete in
        // config/admin.php, so the role needs them granted.
        foreach (['tags.view', 'tags.create', 'tags.update', 'tags.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'tags',
            ])->getKey());
        }

        $this->actingAs($admin, 'admin');
    }

    // ------------------------------------------------------------------ index

    public function test_the_index_lists_tags_with_a_product_count(): void
    {
        Tag::factory()->create(['name' => 'Summer Sale']);
        Tag::factory()->create(['name' => 'Wool']);

        $used = Tag::where('name', 'Wool')->firstOrFail();
        Product::factory()->create()->tags()->attach($used);

        $this->get('/admin/tags')
            ->assertOk()
            ->assertSee('Summer Sale')
            ->assertSee('Wool')
            ->assertSee('in use');
    }

    public function test_the_index_is_ordered_by_name(): void
    {
        Tag::factory()->create(['name' => 'Zebra']);
        Tag::factory()->create(['name' => 'Apple']);

        $names = $this->get('/admin/tags')->viewData('tags')->pluck('name')->all();

        $this->assertSame(['Apple', 'Zebra'], $names);
    }

    public function test_the_index_shows_an_empty_state(): void
    {
        $this->get('/admin/tags')->assertOk()->assertSee('No tags found.');
    }

    // ------------------------------------------------------------------ write

    public function test_a_tag_can_be_created_and_its_slug_is_derived(): void
    {
        $this->post('/admin/tags', ['name' => 'Summer Sale 2026'])
            ->assertRedirect(route('admin.tags.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tags', [
            'name' => 'Summer Sale 2026',
            'slug' => 'summer-sale-2026',
        ]);
    }

    public function test_a_tag_can_be_renamed_and_its_slug_follows(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->put('/admin/tags/'.$tag->getKey(), ['name' => 'Brand New Name'])
            ->assertRedirect(route('admin.tags.index'));

        $tag->refresh();

        $this->assertSame('Brand New Name', $tag->name);
        $this->assertSame('brand-new-name', $tag->slug);
    }

    public function test_saving_a_tag_without_renaming_it_is_not_a_conflict(): void
    {
        // The update check has to exclude the tag being edited, or every save
        // reports a slug collision with itself.
        $tag = Tag::factory()->create(['name' => 'Wool', 'slug' => 'wool']);

        $this->put('/admin/tags/'.$tag->getKey(), ['name' => 'Wool'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Wool', $tag->fresh()->name);
    }

    // ------------------------------------------------------------------ rules

    public function test_two_names_that_produce_the_same_slug_are_rejected_not_crashed(): void
    {
        Tag::factory()->create(['name' => 'Summer Sale', 'slug' => 'summer-sale']);

        // The name is different, so a name-only uniqueness check passes it. Only
        // validating the derived slug catches this before the database does.
        $this->post('/admin/tags', ['name' => 'summer sale'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Tag::count());
    }

    public function test_renaming_onto_another_tags_slug_is_rejected(): void
    {
        Tag::factory()->create(['name' => 'Summer Sale', 'slug' => 'summer-sale']);
        $other = Tag::factory()->create(['name' => 'Winter Sale', 'slug' => 'winter-sale']);

        $this->put('/admin/tags/'.$other->getKey(), ['name' => 'Summer Sale'])
            ->assertSessionHasErrors('name');

        $this->assertSame('winter-sale', $other->fresh()->slug, 'The name must not change either.');
    }

    public function test_a_name_that_cannot_produce_a_slug_is_rejected(): void
    {
        // "!!!" slugifies to an empty string. Storing that twice would trip the
        // unique index, so the form has to refuse it first.
        $this->post('/admin/tags', ['name' => '!!!'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Tag::count());
    }

    public function test_the_name_is_required(): void
    {
        $this->post('/admin/tags', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_a_submitted_slug_is_ignored(): void
    {
        // The slug is server-derived; a crafted post must not be able to set it.
        $this->post('/admin/tags', ['name' => 'Summer Sale', 'slug' => 'hijacked'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tags', ['name' => 'Summer Sale', 'slug' => 'summer-sale']);
    }

    // ----------------------------------------------------------------- delete

    public function test_deleting_a_tag_detaches_it_from_its_products(): void
    {
        $tag = Tag::factory()->create();
        $product = Product::factory()->create();
        $product->tags()->attach($tag);

        $this->assertDatabaseHas('product_tags', [
            'product_id' => $product->getKey(),
            'tag_id' => $tag->getKey(),
        ]);

        $this->delete('/admin/tags/'.$tag->getKey())
            ->assertRedirect(route('admin.tags.index'));

        $this->assertDatabaseMissing('tags', ['_id' => $tag->getKey()]);

        // product_tags has no foreign key and no cascade, so without the
        // model's deleting hook these rows would survive the tag.
        $this->assertDatabaseMissing('product_tags', [
            'product_id' => $product->getKey(),
            'tag_id' => $tag->getKey(),
        ]);
    }

    public function test_deleting_an_unused_tag_leaves_nothing_behind(): void
    {
        $tag = Tag::factory()->create();

        $this->delete('/admin/tags/'.$tag->getKey());

        $this->assertSame(0, Tag::count());
        $this->assertSame(0, DB::table('product_tags')->count());
    }

    // ---------------------------------------------------------------- factory

    public function test_the_factory_builds_a_tag_with_a_matching_slug(): void
    {
        $tag = Tag::factory()->create(['name' => 'Merino Wool']);

        $this->assertSame('merino-wool', $tag->slug);

        // The slug index is unique, so two factory tags must not collide either.
        Tag::factory()->count(5)->create();

        $this->assertSame(6, Tag::count());
    }
}
