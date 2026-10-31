<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the category screens
 * (resources/views/admin/category/{index,create,edit}.blade.php).
 *
 * Defects these screens shipped with:
 *
 *  1. CategoryService::getAll() applied the `active` scope, so the admin list
 *     omitted every deactivated category. A category switched off with the
 *     status toggle disappeared from the screen with no way to find or switch
 *     it back on.
 *  2. Deleting a category that still had products threw a bare \Exception from
 *     the service, which surfaced as an HTTP 500 error page instead of a
 *     validation message naming the category.
 *  3. The edit screen called CKEDITOR.replace() without loading the editor, so
 *     it raised a ReferenceError in the browser and the description fell back
 *     to a plain textarea.
 *  4. The list rendered a flat table, so a child category was indistinguishable
 *     from a top-level one with a parent of the same name, and child counts
 *     were never surfaced at all.
 */
class AdminCategoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'category-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['categories.view', 'categories.create', 'categories.update', 'categories.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'categories',
            ])->getKey());
        }

        return $admin;
    }

    /**
     * The table body only.
     *
     * The filter dropdowns and the stat cards deliberately name every category in
     * the catalogue, so asserting against the whole page would match a name that
     * appears only as a filter option rather than as a listed row.
     */
    protected function page(User $actor, string $query = ''): string
    {
        $html = $this->actingAs($actor, 'admin')
            ->get(route('admin.categories.index').$query)
            ->assertOk()
            ->getContent();

        preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $body);

        return $body[1] ?? $html;
    }

    /**
     * The storefront home page category grid only.
     *
     * The site header names every active top-level category in the main
     * navigation regardless of the home page flag, so asserting against the
     * whole page would match a category that is hidden from the grid but still
     * reachable in the nav — the exact distinction under test. The section is
     * anchored on its own heading and is absent entirely once the grid is empty.
     */
    protected function homeCategoryGrid(): string
    {
        $html = $this->get('/')
            ->assertOk()
            ->getContent();

        preg_match('/Shop by Category.*?<\/section>/s', $html, $grid);

        return $grid[0] ?? '';
    }

    public function test_a_deactivated_category_is_still_listed(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Visible Cat', 'slug' => 'visible-cat', 'is_active' => true]);
        Category::create(['name' => 'Dormant Cat', 'slug' => 'dormant-cat', 'is_active' => false]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Visible Cat', $html);
        $this->assertStringContainsString('Dormant Cat', $html);
    }

    public function test_the_list_can_be_narrowed_to_inactive_categories(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Visible Cat', 'slug' => 'visible-cat', 'is_active' => true]);
        Category::create(['name' => 'Dormant Cat', 'slug' => 'dormant-cat', 'is_active' => false]);

        $html = $this->page($admin, '?status=inactive');

        $this->assertStringContainsString('Dormant Cat', $html);
        $this->assertStringNotContainsString('Visible Cat', $html);
    }

    public function test_deleting_a_category_that_still_has_products_reports_a_validation_error(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Pan']);
        $product->categories()->attach($category);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category->getKey()))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('categories', ['_id' => $category->getKey()]);
    }

    public function test_a_category_with_no_products_can_be_deleted(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.categories.destroy', $category->getKey()))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('categories', ['_id' => $category->getKey()]);
    }

    public function test_the_edit_screen_loads_the_editor_it_initialises(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.edit', $category->getKey()))
            ->assertOk()
            ->getContent();

        // The create screen shipped both; the edit screen called replace()
        // without the script tag, so CKEDITOR was undefined in the browser.
        $this->assertStringContainsString('vendor/ckeditor/ckeditor.js', $html);
        $this->assertStringContainsString('CKEDITOR.replace', $html);
    }

    public function test_child_categories_are_indented_and_counted(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
        $child = Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true, 'parent_id' => $parent->getKey()]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Kitchen', $html);
        $this->assertStringContainsString('Cookware', $html);
        // The child count is what tells an operator a branch still has members.
        $this->assertStringContainsString('1 child', $html);
    }

    public function test_the_list_reports_direct_and_nested_product_counts(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
        $child = Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true, 'parent_id' => $parent->getKey()]);

        $childProduct = Product::factory()->create(['name' => 'Pan']);
        $childProduct->categories()->attach($child);

        $html = $this->page($admin);

        // A top-level category holding no products of its own still has to
        // report what sits beneath it, or a whole branch looks empty.
        $this->assertStringContainsString('1 product in subcategories', $html);
    }

    public function test_the_search_filter_narrows_by_name_and_slug(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
        Category::create(['name' => 'Outdoor', 'slug' => 'outdoor-gear', 'is_active' => true]);

        $byName = $this->page($admin, '?search=Outdoor');
        $this->assertStringContainsString('Outdoor', $byName);
        $this->assertStringNotContainsString('Kitchen', $byName);

        $bySlug = $this->page($admin, '?search=kitchen');
        $this->assertStringContainsString('Kitchen', $bySlug);
        $this->assertStringNotContainsString('Outdoor', $bySlug);
    }

    public function test_the_status_filter_value_is_validated(): void
    {
        $admin = $this->admin();
        Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.index', ['status' => 'bogus']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertStringContainsString('Kitchen', $this->page($admin));
    }

    public function test_the_page_renders_with_no_categories(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No categories', $html);
    }

    public function test_each_row_exposes_its_actions(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);

        $html = $this->page($admin);

        $this->assertStringContainsString(route('admin.categories.edit', $category->getKey()), $html);
        $this->assertStringContainsString(route('admin.categories.destroy', $category->getKey()), $html);
        $this->assertStringContainsString(route('admin.categories.toggleStatus', $category->getKey()), $html);
    }

    public function test_the_parent_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
        Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true, 'parent_id' => $parent->getKey()]);
        Category::create(['name' => 'Outdoor', 'slug' => 'outdoor', 'is_active' => true]);

        $html = $this->page($admin, '?parent='.$parent->getKey());

        $this->assertStringContainsString('Cookware', $html);
        $this->assertStringNotContainsString('Outdoor', $html);
    }

    public function test_toggling_status_flips_the_category(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.categories.toggleStatus', $category->getKey()))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_creating_a_category_reports_a_validation_error_on_the_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertRedirect(route('admin.categories.create'));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Category name is required.', $html);
    }

    public function test_the_edit_screen_offers_a_show_on_home_toggle(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true, 'show_on_home' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.edit', $category->getKey()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="show_on_home"', $html);
        $this->assertMatchesRegularExpression('/type="checkbox"[^>]*name="show_on_home"[^>]*checked/', $html);
    }

    public function test_unticking_show_on_home_removes_the_category_from_the_home_page_grid_only(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true, 'show_on_home' => true]);

        $this->assertStringContainsString('kitchen', $this->homeCategoryGrid());

        $this->actingAs($admin, 'admin')
            ->put(route('admin.categories.update', $category->getKey()), [
                'name' => 'Kitchen',
                'slug' => 'kitchen',
                'show_on_home' => '0',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertFalse($category->fresh()->show_on_home);
        $this->assertStringNotContainsString('kitchen', $this->homeCategoryGrid());

        // Still a live storefront category: the flag only governs the home page.
        $this->get(route('category.show', 'kitchen'))->assertOk();
        $this->assertStringContainsString('Kitchen', $this->page($admin));
    }

    /**
     * The hidden zero is what makes unticking stick.
     *
     * The rule is `sometimes|boolean`, so a submitted request with no
     * `show_on_home` key at all is valid and leaves the stored value alone. A
     * checkbox without its paired hidden field submits exactly that request
     * when unticked, so the flag would silently never turn off.
     */
    public function test_a_request_omitting_show_on_home_leaves_the_stored_value_untouched(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true, 'show_on_home' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.categories.update', $category->getKey()), [
                'name' => 'Kitchen Renamed',
                'slug' => 'kitchen',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertTrue($category->fresh()->show_on_home);
    }

    public function test_a_new_category_is_ticked_for_the_home_page_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.categories.store'), ['name' => 'Kitchen', 'slug' => 'kitchen'])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::where('slug', 'kitchen')->firstOrFail();

        $this->assertTrue($category->show_on_home);
        $this->assertStringContainsString('kitchen', $this->homeCategoryGrid());
    }
}
