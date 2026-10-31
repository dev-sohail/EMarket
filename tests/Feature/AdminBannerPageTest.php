<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin banners screens
 * (resources/views/admin/banners/{index,create,edit}.blade.php and the shared
 * _form / _form-scripts partials they render).
 *
 * Three defects these pages have shipped before:
 *
 *  1. The list was a 13-column table that pushed every useful column off
 *     screen; title, subtitle and description were all separate columns.
 *  2. `admin.banners.toggleStatus` was routed but never rendered, so a banner
 *     could only be deactivated by opening the edit form.
 *  3. create/edit each carried their own inline position script. They drifted —
 *     edit forgot to run `applyPosition` on load for a missing position — and a
 *     single Blade syntax error took both screens down at once.
 */
class AdminBannerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'banners-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        foreach (['banners.view', 'banners.create', 'banners.update', 'banners.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'banners',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin, string $query = ''): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/banners'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_renders_with_a_banner_present(): void
    {
        Banner::create([
            'title' => 'Summer Sale',
            'subtitle' => 'New season',
            'position' => 'homepage',
            'cta_text' => 'Shop Now',
            'is_active' => true,
        ]);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('Summer Sale', $html);
        $this->assertStringContainsString('New season', $html);
        $this->assertStringContainsString('Shop Now', $html);
        // The position reads as a labelled badge, not a raw enum value.
        $this->assertStringContainsString('Homepage', $html);
    }

    public function test_the_page_renders_when_empty(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No banners found', $html);
    }

    public function test_every_row_exposes_edit_toggle_and_delete_actions(): void
    {
        $banner = Banner::create(['title' => 'Summer Sale', 'position' => 'homepage']);

        $html = $this->page($this->admin());

        $this->assertStringContainsString(route('admin.banners.edit', $banner->getKey()), $html);
        $this->assertStringContainsString(route('admin.banners.toggleStatus', $banner->getKey()), $html);
        $this->assertStringContainsString(route('admin.banners.destroy', $banner->getKey()), $html);
    }

    public function test_the_schedule_state_reads_from_the_window(): void
    {
        Banner::create([
            'title' => 'Expired banner',
            'position' => 'homepage',
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
        Banner::create([
            'title' => 'Future banner',
            'position' => 'homepage',
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        Banner::create(['title' => 'Hidden banner', 'position' => 'homepage', 'is_active' => false]);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('Expired', $html);
        $this->assertStringContainsString('Scheduled', $html);
        $this->assertStringContainsString('Inactive', $html);
    }

    public function test_the_position_filter_narrows_the_list(): void
    {
        Banner::create(['title' => 'Hero banner', 'position' => 'homepage']);
        Banner::create(['title' => 'Sale banner', 'position' => 'sale']);

        $html = $this->page($this->admin(), '?position=sale');

        $this->assertStringContainsString('Sale banner', $html);
        $this->assertStringNotContainsString('Hero banner', $html);
    }

    public function test_the_search_filter_matches_the_subtitle(): void
    {
        Banner::create(['title' => 'Hero banner', 'position' => 'homepage']);
        Banner::create(['title' => 'Winter', 'subtitle' => 'Coats and scarves', 'position' => 'homepage']);

        $html = $this->page($this->admin(), '?search=scarves');

        $this->assertStringContainsString('Winter', $html);
        $this->assertStringNotContainsString('Hero banner', $html);
    }

    public function test_an_invalid_filter_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/banners?position=nowhere')
            ->assertRedirect()
            ->assertSessionHasErrors('position');
    }

    public function test_the_stats_counts_reflect_the_window(): void
    {
        Banner::create([
            'title' => 'Live banner',
            'position' => 'homepage',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $html = $this->page($this->admin(), '?state=live');

        $this->assertStringContainsString('Live banner', $html);
    }

    public function test_toggling_status_flips_the_banner(): void
    {
        $banner = Banner::create(['title' => 'Summer Sale', 'position' => 'homepage', 'is_active' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->patch('/admin/banners/'.$banner->getKey().'/toggle-status')
            ->assertRedirect();

        $this->assertFalse($banner->fresh()->is_active);
    }

    public function test_the_create_form_renders_every_position_and_the_preview(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')
            ->get('/admin/banners/create')
            ->assertOk()
            ->getContent();

        foreach (['homepage', 'promotional', 'sale', 'footer'] as $position) {
            $this->assertStringContainsString('value="'.$position.'"', $html);
        }

        $this->assertStringContainsString('id="banner-form"', $html);
        $this->assertStringContainsString('id="live-preview"', $html);
        $this->assertStringContainsString('name="title"', $html);
    }

    public function test_the_edit_form_prefills_the_stored_banner(): void
    {
        $banner = Banner::create([
            'title' => 'Summer Sale',
            'subtitle' => 'New season',
            'position' => 'sale',
            'color' => '#112233',
            'is_active' => false,
        ]);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get('/admin/banners/'.$banner->getKey().'/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="Summer Sale"', $html);
        $this->assertStringContainsString('value="#112233"', $html);
        $this->assertStringContainsString('value="sale" selected', $html);
        $this->assertStringContainsString('id="field-color"', $html);
        $this->assertStringContainsString('id="field-image"', $html);
        // The position config is applied on load, otherwise an edit opens with
        // every field showing until the position select is touched.
        $this->assertStringContainsString('applyPosition(currentPosition())', $html);
    }
}
