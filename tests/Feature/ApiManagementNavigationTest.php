<?php

namespace Tests\Feature;

use App\Models\ApiDefinition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API Management screens were unreachable: ApiDefinitionController existed
 * with form requests and a model, but no routes and no views, so nothing in the
 * back office could reach it.
 *
 * These tests cover the wiring that was missing - the nav link, the routes, and
 * the three views - plus two bugs the wiring exposed:
 *
 *  - the controller ordered by a `sort_order` column that the
 *    create_api_management_tables migration never created, so the index threw
 *    "Unknown column 'sort_order' in 'order clause'" and 500'd;
 *  - the route placeholder had to be {apiDefinition}, not {definition},
 *    because UpdateApiDefinitionRequest reads $this->route('apiDefinition') to
 *    build its unique-ignore rule.
 */
class ApiManagementNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'api-nav-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // Definition CRUD is gated on api_management.* in config/admin.php.
        foreach (['api_management.view', 'api_management.create', 'api_management.update', 'api_management.delete'] as $name) {
            $admin->roles->first()->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'api_management',
            ])->getKey());
        }

        return $admin;
    }

    public function test_the_named_routes_exist(): void
    {
        $names = [
            'admin.api-management.definitions.index',
            'admin.api-management.definitions.create',
            'admin.api-management.definitions.store',
            'admin.api-management.definitions.edit',
            'admin.api-management.definitions.update',
            'admin.api-management.definitions.destroy',
        ];

        foreach ($names as $name) {
            $this->assertNotNull(
                route($name, ['apiDefinition' => 'apid_x'], false),
                "Route [{$name}] is not registered, so the nav link cannot resolve."
            );
        }
    }

    public function test_the_sidebar_link_renders_on_every_admin_page(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $html = $this->get('/admin')->getContent();

        $this->assertStringContainsString(
            route('admin.api-management.definitions.index'),
            $html,
            'The admin sidebar has no link to API Management.'
        );
        $this->assertStringContainsString('API Management', $html);
    }

    public function test_the_index_page_lists_definitions(): void
    {
        $this->actingAs($this->admin(), 'admin');

        // Regression: this ordered by a non-existent sort_order column and 500'd.
        ApiDefinition::create(['name' => 'Storefront', 'slug' => 'storefront', 'source' => 'manual']);

        $this->get('/admin/api-management')
            ->assertOk()
            ->assertSee('Storefront')
            ->assertSee('storefront')
            ->assertDontSee('No API definitions yet.');
    }

    public function test_the_index_shows_the_empty_state_without_definitions(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/api-management')
            ->assertOk()
            ->assertSee('No API definitions yet.');
    }

    public function test_a_definition_can_be_created(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/api-management/create')->assertOk()->assertSee('Create API Definition');

        $response = $this->post('/admin/api-management', [
            '_token' => session()->token(),
            'name' => 'Storefront API',
            'slug' => 'storefront',
            'description' => 'Public catalogue endpoints',
            'source' => 'manual',
            'is_active' => '1',
        ]);

        $definition = ApiDefinition::first();

        $this->assertNotNull($definition, 'The definition was not persisted.');
        $response->assertRedirect(route('admin.api-management.definitions.edit', $definition));
        $response->assertSessionHas('success', 'API definition created successfully.');

        $this->assertSame('Storefront API', $definition->name);
        $this->assertTrue($definition->is_active);
    }

    public function test_the_slug_must_be_unique(): void
    {
        $this->actingAs($this->admin(), 'admin');

        ApiDefinition::create(['name' => 'First', 'slug' => 'storefront', 'source' => 'manual']);

        $this->post('/admin/api-management', [
            '_token' => session()->token(),
            'name' => 'Second',
            'slug' => 'storefront',
            'source' => 'manual',
        ])->assertSessionHasErrors('slug');

        $this->assertSame(1, ApiDefinition::count(), 'The duplicate definition must not be created.');
    }

    /**
     * A definition must be able to keep its own slug on save. The unique-ignore
     * rule is built from $this->route('apiDefinition'), so a mismatched route
     * placeholder makes every edit fail with "slug has already been taken".
     */
    public function test_a_definition_can_be_saved_without_changing_its_slug(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create(['name' => 'Original', 'slug' => 'storefront', 'source' => 'manual']);

        $this->put(route('admin.api-management.definitions.update', $definition->getKey()), [
            '_token' => session()->token(),
            'name' => 'Renamed',
            'slug' => 'storefront',
            'source' => 'manual',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $definition->fresh()->name);
    }

    public function test_unticking_the_active_box_deactivates_the_definition(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create([
            'name' => 'Active', 'slug' => 'storefront', 'source' => 'manual', 'is_active' => true,
        ]);

        // The form pairs a hidden is_active=0 with the checkbox so an unticked
        // box is still submitted.
        $this->put(route('admin.api-management.definitions.update', $definition->getKey()), [
            '_token' => session()->token(),
            'name' => 'Active',
            'slug' => 'storefront',
            'source' => 'manual',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($definition->fresh()->is_active);
    }

    public function test_a_definition_can_be_deleted_soft(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create(['name' => 'Doomed', 'slug' => 'doomed', 'source' => 'manual']);

        $this->delete(route('admin.api-management.definitions.destroy', $definition->getKey()))
            ->assertRedirect(route('admin.api-management.definitions.index'))
            ->assertSessionHas('success', 'API definition deleted successfully.');

        $this->assertSoftDeleted('api_definitions', ['_id' => $definition->getKey()]);
    }

    public function test_the_screens_require_an_admin_session(): void
    {
        $this->get('/admin/api-management')->assertRedirect(route('admin.login'));
        $this->get('/admin/api-management/create')->assertRedirect(route('admin.login'));
    }
}
