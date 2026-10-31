<?php

namespace Tests\Feature;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\ApiRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminReflectionUiTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['email' => 'reflect-admin@example.test', 'is_active' => true]);
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'r']);
        $admin->assignRole('Admin');

        // The reflect screens sit behind the api_management.* permissions in
        // config/admin.php; without them AdminMiddleware 403s every request.
        foreach (['api_management.view', 'api_management.update'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'api_management',
            ])->getKey());
        }

        return $admin;
    }

    protected function registerApiRoutes(): void
    {
        Route::middleware(['api'])->prefix('api')
            ->group(fn () => app(ApiRouteRegistrar::class)->register());
    }

    public function test_the_reflect_screen_lists_reflectable_models(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/api-management/reflect')
            ->assertOk()
            ->assertSee('Reflect API from Model')
            ->assertSee('App\Models\Product')
            // Unreflectable models are listed with a reason, not hidden.
            ->assertSee('ProductAttributeValue')
            ->assertSee('has no primary key');
    }

    public function test_an_admin_can_generate_a_definition_from_the_ui(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/api-management/reflect', ['model' => 'Product'])
            ->assertRedirect();

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();

        $this->assertSame('reflection', $definition->source);
        $this->assertFalse($definition->is_active, 'Reflection must not publish a URL unasked.');
        $this->assertSame(2, $definition->endpoints()->count());
    }

    public function test_generating_from_the_ui_activates_only_when_asked(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/api-management/reflect', ['model' => 'Product', 'activate' => '1'])
            ->assertRedirect();

        $this->assertTrue(ApiDefinition::where('slug', 'products')->firstOrFail()->is_active);
    }

    public function test_an_unknown_model_is_rejected_on_the_field_not_as_an_error(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/api-management/reflect')
            ->post('/admin/api-management/reflect', ['model' => 'NoSuchModel'])
            ->assertRedirect('/admin/api-management/reflect')
            ->assertSessionHasErrors('model');

        $this->assertSame(0, ApiDefinition::count());
    }

    public function test_a_keyless_model_is_rejected_with_its_reason(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/api-management/reflect')
            ->post('/admin/api-management/reflect', ['model' => 'ProductAttributeValue'])
            ->assertSessionHasErrors('model');

        $this->assertSame(0, ApiDefinition::count());
    }

    public function test_a_url_collision_is_reported_rather_than_thrown(): void
    {
        $this->actingAs($this->admin(), 'admin');

        ApiDefinition::create(['name' => 'Legacy', 'slug' => 'legacy', 'source' => 'manual', 'is_active' => true]);
        ApiEndpoint::create([
            'api_definition_id' => ApiDefinition::where('slug', 'legacy')->firstOrFail()->getKey(),
            'method' => 'GET', 'path' => 'products/{id}', 'action' => 'S::x',
            'handler_type' => 'service', 'requires_auth' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->from('/admin/api-management/reflect')
            ->post('/admin/api-management/reflect', ['model' => 'Product'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(ApiDefinition::where('slug', 'products')->first());
    }

    public function test_the_edit_form_offers_regeneration_and_locks_the_slug(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/api-management/reflect', ['model' => 'Product', 'activate' => '1']);

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();

        $html = $this->get('/admin/api-management/'.$definition->getKey().'/edit')->getContent();

        $this->assertStringContainsString('Regenerate from model', $html);
        // The slug is the reflector's key, so it is shown but not editable.
        $this->assertStringNotContainsString('name="slug"', $html);
    }

    public function test_a_crafted_post_cannot_rename_a_reflected_definition(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/api-management/reflect', ['model' => 'Product']);

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();

        $this->put('/admin/api-management/'.$definition->getKey(), [
            'name' => 'Renamed', 'slug' => 'hijacked', 'description' => 'x', 'source' => 'manual',
        ])->assertSessionHasNoErrors();

        $definition->refresh();

        $this->assertSame('products', $definition->slug, 'The slug must stay pinned to the model table.');
        $this->assertSame('reflection', $definition->source, 'source must not be client-controlled.');
        $this->assertSame('Renamed', $definition->name, 'Editable fields still save.');
    }

    public function test_a_crafted_post_cannot_relabel_a_manual_definition_as_reflected(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create([
            'name' => 'Handmade', 'slug' => 'handmade', 'source' => 'manual', 'is_active' => true,
        ]);

        $this->put('/admin/api-management/'.$definition->getKey(), [
            'name' => 'Handmade', 'slug' => 'handmade', 'source' => 'reflection',
        ])->assertSessionHasNoErrors();

        $this->assertSame('manual', $definition->fresh()->source);
    }

    public function test_a_manual_definition_can_still_change_its_slug(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create([
            'name' => 'Handmade', 'slug' => 'handmade', 'source' => 'manual', 'is_active' => true,
        ]);

        $this->put('/admin/api-management/'.$definition->getKey(), [
            'name' => 'Handmade', 'slug' => 'handmade-v2',
        ])->assertSessionHasNoErrors();

        $this->assertSame('handmade-v2', $definition->fresh()->slug);
    }

    public function test_the_regenerate_action_updates_an_existing_reflected_definition(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/api-management/reflect', ['model' => 'Product', 'activate' => '1']);

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();
        $definition->update(['name' => 'Will Be Overwritten']);

        $this->post('/admin/api-management/'.$definition->getKey().'/regenerate')
            ->assertRedirect();

        $this->assertSame('Products', $definition->fresh()->name);
        $this->assertTrue($definition->fresh()->is_active, 'Regeneration must not deactivate a live API.');
        $this->assertSame(2, $definition->endpoints()->count());
    }

    public function test_a_manual_definition_cannot_be_regenerated(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $definition = ApiDefinition::create([
            'name' => 'Handmade', 'slug' => 'handmade', 'source' => 'manual', 'is_active' => true,
        ]);
        $endpoint = ApiEndpoint::create([
            'api_definition_id' => $definition->getKey(), 'method' => 'GET', 'path' => 'custom',
            'action' => 'App\Services\Api\StoreCatalogService::products', 'handler_type' => 'service',
            'requires_auth' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->post('/admin/api-management/'.$definition->getKey().'/regenerate')
            ->assertSessionHas('error');

        $this->assertSame(1, $definition->endpoints()->count(), 'Hand-written endpoints must survive.');
    }

    public function test_the_endpoint_form_flags_a_generated_endpoint(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/api-management/reflect', ['model' => 'Product']);

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();
        $endpoint = $definition->endpoints()->firstWhere('path', 'products');

        $this->get('/admin/api-management/'.$definition->getKey().'/endpoints/'.$endpoint->getKey().'/edit')
            ->assertOk()
            ->assertSee('generated by reflection');
    }

    public function test_a_ui_generated_definition_serves_traffic(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/api-management/reflect', ['model' => 'Product', 'activate' => '1']);

        Product::factory()->create(['name' => 'Reflected In', 'slug' => 'reflected-in']);

        $this->registerApiRoutes();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Reflected In');
    }
}
