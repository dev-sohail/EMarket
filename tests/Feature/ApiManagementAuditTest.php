<?php

namespace Tests\Feature;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\StoreCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every mutation of the API surface must leave an audit trail.
 *
 * The API surface is the most security-relevant thing in this admin: activating
 * an endpoint publishes a live URL, and reflection publishes a whole API from a
 * model in one action. Nothing here was audited before, so the log could not
 * answer "who turned this on".
 *
 * The assertions read the AuditLog rows directly rather than the admin screen,
 * because the screen is presentation; a test that only greps HTML would pass
 * with an entry that is stored wrong, and one that only checks the database
 * would miss an entry the screen cannot display. `test_the_reflection_entries_are
 * visible_on_the_audit_screen` covers the second half.
 */
class ApiManagementAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'audit-admin@example.test',
            'is_active' => true,
        ]);

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $this->admin->assignRole('Admin');

        // The audit screen is permission-gated on audit_logs.view (config/admin.php),
        // so the assertions that read it would 403 without this grant. The
        // reflect/regenerate/store/endpoints calls that produce the audit rows
        // sit behind the api_management.* permissions.
        foreach (['audit_logs.view', 'api_management.view', 'api_management.create', 'api_management.update', 'api_management.delete'] as $name) {
            $permission = Permission::firstOrCreate(
                ['slug' => str_replace('.', '-', $name)],
                ['name' => $name, 'group_name' => explode('.', $name)[0]]
            );

            Role::where('slug', 'admin')->firstOrFail()->permissions()->syncWithoutDetaching([$permission->getKey()]);
        }

        $this->actingAs($this->admin, 'admin');
    }

    protected function reflectProduct(array $payload = []): void
    {
        $this->post('/admin/api-management/reflect', $payload + ['model' => 'Product']);
    }

    // ------------------------------------------------------------- reflection

    public function test_generating_from_the_ui_is_audited(): void
    {
        $this->reflectProduct(['activate' => '1']);

        $log = AuditLog::where('action', 'reflected')->firstOrFail();

        $this->assertSame(ApiDefinition::class, $log->entity_type);
        $this->assertSame($this->admin->getKey(), $log->user_id, 'The acting admin must be recorded.');
        $this->assertSame(
            ApiDefinition::where('slug', 'products')->firstOrFail()->getKey(),
            $log->entity_id
        );

        $values = $log->new_values;

        // The model is the only way to explain later why this API exists.
        $this->assertSame(Product::class, $values['reflected_model']);
        $this->assertSame(['products', 'products/{product}'], $values['paths']);
        $this->assertSame(2, $values['endpoint_count']);
        $this->assertGreaterThan(0, $values['parameter_count']);
        // Reflection defaults to public; the log has to say so.
        $this->assertSame(['products', 'products/{product}'], $values['public_endpoints']);
    }

    public function test_regenerating_is_audited_as_regenerated_with_a_real_diff(): void
    {
        $this->reflectProduct();

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();

        // A hand edit that regeneration will silently revert.
        $definition->update(['name' => 'Hand Renamed']);
        AuditLog::query()->delete();

        $this->post('/admin/api-management/'.$definition->getKey().'/regenerate')
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'regenerated')->firstOrFail();

        $this->assertSame('Hand Renamed', $log->old_values['name']);
        $this->assertSame('Products', $log->new_values['name']);
        $this->assertSame(Product::class, $log->new_values['reflected_model']);
    }

    public function test_a_second_generation_is_audited_as_regenerated_not_reflected(): void
    {
        $this->reflectProduct();
        AuditLog::query()->delete();

        $this->reflectProduct();

        // The first run created it; the second overwrote it. Conflating the two
        // would make the trail claim a definition was born twice.
        $this->assertSame(1, AuditLog::where('action', 'regenerated')->count());
        $this->assertSame(0, AuditLog::where('action', 'reflected')->count());
    }

    public function test_a_refused_generation_is_not_audited_as_a_change(): void
    {
        ApiDefinition::create(['name' => 'Legacy', 'slug' => 'legacy', 'source' => 'manual', 'is_active' => true]);
        ApiEndpoint::create([
            'api_definition_id' => ApiDefinition::where('slug', 'legacy')->firstOrFail()->getKey(),
            'method' => 'GET', 'path' => 'products/{id}', 'action' => 'S::x', 'handler_type' => 'service',
            'requires_auth' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->reflectProduct();

        $this->assertSame(0, AuditLog::whereIn('action', ['reflected', 'regenerated'])->count());
    }

    public function test_regenerating_a_manual_definition_is_audited_as_refused(): void
    {
        $definition = ApiDefinition::create([
            'name' => 'Handmade', 'slug' => 'handmade', 'source' => 'manual', 'is_active' => true,
        ]);

        $this->post('/admin/api-management/'.$definition->getKey().'/regenerate')
            ->assertSessionHas('error');

        $this->assertSame(0, AuditLog::where('action', 'regenerated')->count());
    }

    // ------------------------------------------------------------ definitions

    public function test_definition_changes_are_audited(): void
    {
        $this->post('/admin/api-management', ['name' => 'Storefront', 'slug' => 'storefront']);

        $definition = ApiDefinition::where('slug', 'storefront')->firstOrFail();

        $this->put('/admin/api-management/'.$definition->getKey(), [
            'name' => 'Storefront v2', 'slug' => 'storefront',
        ])->assertSessionHasNoErrors();

        $this->delete('/admin/api-management/'.$definition->getKey());

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            AuditLog::orderBy('created_at')->pluck('action')->all()
        );

        $updated = AuditLog::where('action', 'updated')->firstOrFail();
        $this->assertSame('Storefront', $updated->old_values['name']);
        $this->assertSame('Storefront v2', $updated->new_values['name']);

        $deleted = AuditLog::where('action', 'deleted')->firstOrFail();
        $this->assertSame('Storefront v2', $deleted->old_values['name']);

        AuditLog::each(fn (AuditLog $log) => $this->assertSame($this->admin->getKey(), $log->user_id));
    }

    public function test_a_reflected_definition_edit_is_audited(): void
    {
        $this->reflectProduct();

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();

        $this->put('/admin/api-management/'.$definition->getKey(), [
            'name' => 'Catalogue', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'updated')->firstOrFail();

        $this->assertSame('Products', $log->old_values['name']);
        $this->assertSame('Catalogue', $log->new_values['name']);
        $this->assertFalse($log->old_values['is_active']);
        $this->assertTrue($log->new_values['is_active']);
    }

    // -------------------------------------------------------------- endpoints

    public function test_a_created_entry_records_the_stored_row_not_the_in_memory_input(): void
    {
        // The DB default for requires_auth is true. An entry created without the
        // field must still be logged as requiring auth, or the audit trail
        // contradicts the row it describes.
        $definition = ApiDefinition::create([
            'name' => 'Storefront', 'slug' => 'storefront', 'source' => 'manual', 'is_active' => true,
        ]);

        $this->post('/admin/api-management/'.$definition->getKey().'/endpoints', [
            'method' => 'GET',
            'path' => 'store/defaults',
            'action' => StoreCatalogService::class.'::products',
            'handler_type' => 'service',
        ])->assertSessionHasNoErrors();

        $endpoint = ApiEndpoint::where('path', 'store/defaults')->firstOrFail();
        $logged = AuditLog::where('action', 'created')->firstOrFail()->new_values;

        $this->assertTrue($endpoint->requires_auth, 'Precondition: the column default is true.');
        $this->assertTrue($logged['requires_auth'], 'The audit entry must match the stored row.');
        $this->assertSame(0, $logged['sort_order'], 'Defaults must be recorded, not null.');
    }

    public function test_endpoint_changes_are_audited(): void
    {
        $definition = ApiDefinition::create([
            'name' => 'Storefront', 'slug' => 'storefront', 'source' => 'manual', 'is_active' => true,
        ]);

        $this->post('/admin/api-management/'.$definition->getKey().'/endpoints', [
            'method' => 'GET',
            'path' => 'store/products',
            'action' => StoreCatalogService::class.'::products',
            'handler_type' => 'service',
            'requires_auth' => '0',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $endpoint = ApiEndpoint::where('path', 'store/products')->firstOrFail();

        $this->put('/admin/api-management/'.$definition->getKey().'/endpoints/'.$endpoint->getKey(), [
            'method' => 'GET',
            'path' => 'store/products',
            'action' => StoreCatalogService::class.'::products',
            'handler_type' => 'service',
            'requires_auth' => '1',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->delete('/admin/api-management/'.$definition->getKey().'/endpoints/'.$endpoint->getKey());

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            AuditLog::orderBy('created_at')->pluck('action')->all()
        );

        $updated = AuditLog::where('action', 'updated')->firstOrFail();
        $this->assertSame(ApiEndpoint::class, $updated->entity_type);

        // Making an endpoint require auth is the security-relevant change, so
        // it has to survive into the log rather than being flattened.
        $this->assertFalse($updated->old_values['requires_auth']);
        $this->assertTrue($updated->new_values['requires_auth']);
    }

    // ----------------------------------------------------------------- screen

    public function test_the_reflection_entries_are_visible_on_the_audit_screen(): void
    {
        $this->reflectProduct(['activate' => '1']);

        $this->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Reflected')
            ->assertSee('ApiDefinition')
            ->assertSee($this->admin->name);

        // And the entry's detail page, which is where the before/after lives.
        $log = AuditLog::where('action', 'reflected')->firstOrFail();

        $this->get('/admin/audit-logs/'.$log->getKey())
            ->assertOk()
            ->assertSee('reflected_model')
            ->assertSee(Product::class)
            ->assertSee('products/{product}');
    }

    public function test_the_audit_screen_can_filter_by_the_reflection_actions(): void
    {
        $this->reflectProduct();

        // The action filter is a hard-coded <select>; a new action that is not
        // added to it exists in the database but can never be found.
        $this->get('/admin/audit-logs')
            ->assertSee('value="reflected"', false)
            ->assertSee('value="regenerated"', false);

        // 'Reflected' is also the dropdown's own label, so assertDontSee could
        // never pass here. The empty state is the unambiguous signal: the only
        // entry is a 'reflected' one, so filtering for 'regenerated' must find
        // nothing.
        $this->get('/admin/audit-logs?action=reflected')
            ->assertOk()
            ->assertSee('Reflected')
            ->assertDontSee('No audit logs found.');

        $this->get('/admin/audit-logs?action=regenerated')
            ->assertOk()
            ->assertSee('No audit logs found.');
    }
}
