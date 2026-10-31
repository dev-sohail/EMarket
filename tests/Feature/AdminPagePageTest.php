<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin pages screens
 * (resources/views/admin/pages/{index,create,edit}.blade.php).
 *
 * The index was a bare table: every page loaded in full with no search, no
 * filtering and no pagination, so the store grew one query wider on every page
 * an admin added. These tests pin the filter/sort/paginate contract and assert
 * the three views still render, since the stat tiles and the status radio cards
 * are new markup rather than a restyle of the old table.
 */
class AdminPagePageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'pages-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // config/admin.php maps the write routes onto these permissions, so a
        // role without them gets a 403 from AdminMiddleware rather than a
        // missing form.
        foreach (['pages.view', 'pages.create', 'pages.update', 'pages.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'pages',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin, string $query = ''): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/pages'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_index_renders_with_a_page_present(): void
    {
        Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>Hello</p>', 'status' => 'published']);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('About Us', $html);
        $this->assertStringContainsString('/about-us', $html);
    }

    public function test_the_index_renders_when_empty(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No pages yet', $html);
    }

    public function test_search_narrows_the_list_by_title_and_slug(): void
    {
        Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>a</p>', 'status' => 'published']);
        Page::create(['title' => 'Shipping Info', 'slug' => 'shipping', 'content' => '<p>b</p>', 'status' => 'published']);

        $html = $this->page($this->admin(), '?search=Shipping');

        $this->assertStringContainsString('Shipping Info', $html);
        $this->assertStringNotContainsString('About Us', $html);
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        Page::create(['title' => 'Live Page', 'slug' => 'live', 'content' => '<p>a</p>', 'status' => 'published']);
        Page::create(['title' => 'Hidden Page', 'slug' => 'hidden', 'content' => '<p>b</p>', 'status' => 'draft']);

        $html = $this->page($this->admin(), '?status=draft');

        $this->assertStringContainsString('Hidden Page', $html);
        $this->assertStringNotContainsString('Live Page', $html);
    }

    public function test_the_sort_filter_orders_by_title(): void
    {
        Page::create(['title' => 'Zebra', 'slug' => 'zebra', 'content' => '<p>a</p>', 'status' => 'draft']);
        Page::create(['title' => 'Alpha', 'slug' => 'alpha', 'content' => '<p>b</p>', 'status' => 'draft']);

        $html = $this->page($this->admin(), '?sort=title_asc');

        $this->assertLessThan(strpos($html, 'Zebra'), strpos($html, 'Alpha'));
    }

    public function test_an_unknown_status_filter_is_rejected_rather_than_ignored(): void
    {
        Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>a</p>', 'status' => 'published']);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/pages?status=nonsense')
            ->assertSessionHasErrors('status');
    }

    public function test_the_stat_tiles_count_the_whole_library_while_the_table_is_filtered(): void
    {
        Page::create(['title' => 'Live Page', 'slug' => 'live', 'content' => '<p>a</p>', 'status' => 'published']);
        Page::create(['title' => 'Hidden Page', 'slug' => 'hidden', 'content' => '<p>b</p>', 'status' => 'draft']);

        $html = $this->page($this->admin(), '?status=draft');

        // The Published tile still reports the whole library, not the filtered
        // subset — otherwise the count would contradict itself row by row.
        $this->assertMatchesRegularExpression('/Published<\/p>\s*<p[^>]*>1<\/p>/', $html);
        $this->assertMatchesRegularExpression('/Drafts<\/p>\s*<p[^>]*>1<\/p>/', $html);
    }

    public function test_the_list_paginates_past_the_page_size(): void
    {
        foreach (range(1, 20) as $i) {
            Page::create(['title' => 'Page '.$i, 'slug' => 'page-'.$i, 'content' => '<p>a</p>', 'status' => 'draft']);
        }

        $admin = $this->admin();

        $first = $this->page($admin);
        $this->assertStringContainsString('page=2', $first, 'no second page link rendered');

        $second = $this->page($admin, '?page=2');
        $this->assertStringContainsString('Page 20', $second);
        $this->assertStringNotContainsString('Page 1<', $second);
    }

    public function test_every_row_exposes_toggle_and_delete_actions(): void
    {
        $page = Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>a</p>', 'status' => 'published']);

        $html = $this->page($this->admin());

        $this->assertStringContainsString(route('admin.pages.toggleStatus', $page), $html);
        $this->assertStringContainsString(route('admin.pages.destroy', $page), $html);
        $this->assertStringContainsString(route('admin.pages.edit', $page), $html);
    }

    public function test_the_create_and_edit_screens_render(): void
    {
        $admin = $this->admin();
        $page = Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>a</p>', 'status' => 'draft']);

        $create = $this->actingAs($admin, 'admin')->get('/admin/pages/create')->assertOk()->getContent();
        $this->assertStringContainsString('name="title"', $create);
        $this->assertStringContainsString('name="slug"', $create);
        $this->assertStringContainsString('name="content"', $create);
        $this->assertStringContainsString('value="published"', $create, 'create defaults to published');

        $edit = $this->actingAs($admin, 'admin')->get('/admin/pages/'.$page->getKey().'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('About Us', $edit);
        $this->assertStringContainsString('value="about-us"', $edit);
        // A draft must come back selected, otherwise saving the form would
        // silently publish the page.
        $this->assertMatchesRegularExpression('/value="draft"[^>]*\s+checked/', $edit);
    }

    public function test_storing_a_page_persists_it(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/pages', [
                'title' => 'Terms & Conditions',
                'slug' => 'terms',
                'content' => '<p>Terms</p>',
                'status' => 'published',
            ])
            ->assertRedirect('/admin/pages');

        $this->assertDatabaseHas('pages', ['slug' => 'terms', 'status' => 'published']);
    }

    public function test_toggling_status_flips_the_page(): void
    {
        $page = Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>a</p>', 'status' => 'draft']);

        $this->actingAs($this->admin(), 'admin')
            ->patch('/admin/pages/'.$page->getKey().'/toggle-status')
            ->assertRedirect();

        $this->assertSame('published', $page->fresh()->status);
    }
}
