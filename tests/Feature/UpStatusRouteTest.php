<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The system status page used to be a public route with no middleware. It renders
 * the PHP and Laravel versions, APP_URL, APP_ENV, whether APP_DEBUG is on, the
 * server software banner, and which integrations are enabled, and its database
 * branch concatenated the raw PDO exception into the response — which reads
 * "SQLSTATE[HY000] [1045] Access denied for user 'poweredin'@'localhost'" and so
 * disclosed the DB username and host to an anonymous caller at exactly the moment
 * the database was unreachable.
 */
class UpStatusRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Test']);

        foreach (['audit_logs.view'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'audit',
            ])->getKey());
        }

        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_the_status_page_is_not_reachable_anonymously(): void
    {
        // Not /admin/*, so the redirect goes to the storefront login rather than
        // admin.login — what matters is that it is a redirect and not a 200.
        $this->get('/up-status')->assertRedirect(route('login'));
    }

    public function test_the_status_page_is_not_reachable_by_a_signed_in_customer(): void
    {
        // A customer holds an account but nothing on the `admin` guard, so
        // `auth:admin` sends them back to a login page rather than rendering
        // the environment inventory.
        $customer = User::factory()->create(['is_active' => true]);

        $this->actingAs($customer, 'admin')->get('/up-status')->assertRedirect();
    }

    public function test_an_admin_holding_the_permission_can_still_read_it(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/up-status')
            ->assertOk();
    }

    public function test_the_database_branch_never_renders_the_raw_exception(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->get('/up-status');

        $response->assertOk();

        $body = $response->getContent();

        // Guard the specific shapes that leaked: PDO/SQLSTATE text and the
        // DB connection details it carries.
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('Access denied for user', $body);
        $this->assertStringNotContainsString('connection failed:', $body);
    }
}
