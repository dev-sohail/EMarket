<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 'password' and 'email_verified_at' were both on App\Models\User::$fillable.
 *
 * The `hashed` cast meant a mass-assigned password was still hashed, so nothing
 * was stored in plaintext — but any future `$user->update($request->validated())`
 * whose rules happened to include `password` would have been one refactor away
 * from writing whatever the request carried, and `email_verified_at` was forgeable
 * through any mass-assignment path on a model where it did not belong.
 *
 * These tests pin the consequence: the fields stay off $fillable, the legitimate
 * writers still work, and the ones that do not go through mass assignment.
 */
class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_and_email_verified_at_are_not_mass_assignable(): void
    {
        $fillable = (new User)->getFillable();

        $this->assertNotContains('password', $fillable);
        $this->assertNotContains('email_verified_at', $fillable);
    }

    public function test_a_mass_assignment_attempt_cannot_write_a_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $originalVerifiedAt = $user->email_verified_at;

        $user->fill([
            'name' => 'Still Fine',
            'password' => 'attacker-chosen',
            'email_verified_at' => now(),
        ]);

        $this->assertNotSame('attacker-chosen', $user->password);
        $this->assertTrue(
            $originalVerifiedAt->equalTo($user->email_verified_at),
            'email_verified_at must survive a fill() that names it.'
        );

        // The name still applied — the point is the guarded fields were
        // ignored, not that fill() became a no-op.
        $this->assertSame('Still Fine', $user->name);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_public_registration_still_hashes_and_stores_the_password(): void
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace-new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '+41790000000',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'grace-new@example.com')->sole();

        $this->assertNotNull($user->password, 'users.password is NOT NULL; the write must have happened.');
        $this->assertNotSame('secret-password', $user->password, 'The password must not be stored in plaintext.');
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_an_admin_can_still_create_a_user_with_a_password(): void
    {
        $admin = $this->adminWithPermission('users.create', 'roles.create');
        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test']);
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin, 'admin')->post(route('admin.users.store'), [
            'name' => 'Created By Admin',
            'email' => 'made-by-admin@example.com',
            'password' => 'admin-set-password',
            'password_confirmation' => 'admin-set-password',
            'role' => 'Admin',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();

        $created = User::where('email', 'made-by-admin@example.com')->sole();

        $this->assertNotNull($created->password);
        $this->assertTrue(Hash::check('admin-set-password', $created->password));
    }

    public function test_an_admin_password_update_still_hashes(): void
    {
        $admin = $this->adminWithPermission('users.update', 'roles.update');
        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test']);
        $admin->assignRole('Admin');

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.users.update', $target), [
                'name' => 'Renamed',
                'email' => $target->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
                'role' => 'Admin',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-brand-new-password', $target->fresh()->password));
    }

    public function test_a_blank_password_on_update_leaves_the_stored_one_alone(): void
    {
        $admin = $this->adminWithPermission('users.update', 'roles.update');
        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test']);
        $admin->assignRole('Admin');

        $target = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('the-original-password'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.users.update', $target), [
                'name' => 'Renamed Only',
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'Admin',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        // Blank input arrives as null, not '', so this must branch on filled().
        $this->assertTrue(
            Hash::check('the-original-password', $target->fresh()->password),
            'An untouched password field must not overwrite the stored password.'
        );
    }

    protected function adminWithPermission(string ...$permissions): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'Perms', 'slug' => 'perms', 'description' => 'Test']);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'users',
            ])->getKey());
        }

        $admin->assignRole('Perms');

        return $admin;
    }
}
