<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin users screen (resources/views/admin/user/index.blade.php).
 *
 * Defects this page has shipped before:
 *
 *  1. `paginate(20)` with no `links()` call, so anything past the first 20
 *     back-office accounts was unreachable — no way to page forward.
 *  2. Only `$user->roles->first()` was rendered, so a user holding more than
 *     one role showed just the first and the rest were silently invisible.
 *  3. The active admin could see (and use) a Delete button on their own row,
 *     which deletes the account they are signed in as.
 */
class AdminUserPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge([
            'name' => 'Lead Admin',
            'email' => 'lead-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));

        $admin->assignRole($this->adminRole()->name);

        return $admin;
    }

    protected function adminRole(): Role
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test role']
        );

        // admin.users.index (and create/edit) are mapped to users.view in
        // config/admin.php; without it the listing 403s.
        $role->permissions()->syncWithoutDetaching(
            Permission::firstOrCreate(
                ['slug' => 'users-view'],
                ['name' => 'users.view', 'group_name' => 'users']
            )->getKey()
        );

        return $role;
    }

    protected function page(User $actor, string $query = ''): string
    {
        return $this->actingAs($actor, 'admin')
            ->get('/admin/users'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_lists_admins_with_their_role_and_status(): void
    {
        $viewer = $this->admin();
        $other = $this->admin([
            'name' => 'Support Rep',
            'email' => 'support@example.test',
            'is_active' => false,
            'email_verified_at' => null,
        ]);
        $other->syncRoles('Admin');

        $html = $this->page($viewer);

        $this->assertStringContainsString('Support Rep', $html);
        $this->assertStringContainsString('support@example.test', $html);
        $this->assertStringContainsString('Inactive', $html);
        $this->assertStringContainsString('Unverified', $html);
    }

    public function test_the_page_renders_when_no_admin_matches_the_filter(): void
    {
        $viewer = $this->admin();
        $other = $this->admin(['name' => 'Support Rep', 'email' => 'support@example.test']);
        $other->syncRoles('Admin');

        $html = $this->page($viewer, '?q=nobody-matches-this');

        $this->assertStringContainsString('No admin users found', $html);
    }

    public function test_the_pagination_link_is_rendered(): void
    {
        $viewer = $this->admin();

        foreach (range(1, 25) as $i) {
            $user = $this->admin(['name' => 'Extra '.$i, 'email' => 'extra'.$i.'@example.test']);
            $user->syncRoles('Admin');
        }

        $html = $this->page($viewer);

        $this->assertStringContainsString('page=2', $html, 'no second page link rendered');
    }

    public function test_every_held_role_is_rendered_not_just_the_first(): void
    {
        $viewer = $this->admin();
        $multi = $this->admin(['name' => 'Multi Role', 'email' => 'multi@example.test']);

        $second = Role::create(['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read only']);
        $multi->roles()->attach([$viewer->roles->first()->getKey(), $second->getKey()]);

        $html = $this->page($viewer);

        $this->assertStringContainsString('Multi Role', $html);
        $this->assertStringContainsString('Auditor', $html);
    }

    public function test_the_signed_in_admin_cannot_delete_their_own_account(): void
    {
        $viewer = $this->admin();
        $other = $this->admin(['name' => 'Support Rep', 'email' => 'support@example.test']);
        $other->syncRoles('Admin');

        $html = $this->page($viewer);

        // Compare on the full form action, quoted: the edit URL for a row is
        // ".../admin/users/{id}/edit", which contains the destroy URL as a
        // prefix and would make a bare substring check pass for the wrong row.
        $this->assertStringNotContainsString('action="'.route('admin.users.destroy', $viewer->getKey()).'"', $html);
        $this->assertStringContainsString('action="'.route('admin.users.destroy', $other->getKey()).'"', $html);
        $this->assertStringContainsString('You</span>', $html);
    }

    public function test_the_search_filter_narrows_by_name_and_email(): void
    {
        $viewer = $this->admin();
        $match = $this->admin(['name' => 'Warehouse Lead', 'email' => 'warehouse@example.test']);
        $match->syncRoles('Admin');
        $miss = $this->admin(['name' => 'Designer', 'email' => 'designer@example.test']);
        $miss->syncRoles('Admin');

        $html = $this->page($viewer, '?q=warehouse');

        $this->assertStringContainsString('Warehouse Lead', $html);
        $this->assertStringNotContainsString('Designer', $html);
    }

    public function test_the_status_filter_narrows_to_inactive_accounts(): void
    {
        $viewer = $this->admin();
        $inactive = $this->admin(['name' => 'Paused Rep', 'email' => 'paused@example.test', 'is_active' => false]);
        $inactive->syncRoles('Admin');

        $html = $this->page($viewer, '?status=inactive');

        $this->assertStringContainsString('Paused Rep', $html);
        $this->assertStringNotContainsString('support@example.test', $html);
    }

    public function test_the_role_filter_narrows_to_a_single_role(): void
    {
        $viewer = $this->admin();
        $auditorRole = Role::create(['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read only']);
        $auditor = $this->admin(['name' => 'Audit Person', 'email' => 'audit@example.test']);
        $auditor->roles()->attach($auditorRole);

        $html = $this->page($viewer, '?role=Auditor');

        $this->assertStringContainsString('Audit Person', $html);
        $this->assertStringNotContainsString('support@example.test', $html);
    }

    public function test_customers_are_never_listed_as_admins(): void
    {
        $viewer = $this->admin();
        $shopper = User::factory()->create(['email' => 'shopper@example.test', 'is_active' => true]);

        $html = $this->page($viewer);

        $this->assertStringNotContainsString('shopper@example.test', $html);
    }
}
