<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the admin role edit form.
 *
 * The edit form is the only place role permissions can be granted or revoked,
 * so a silent failure to write the pivot is a privilege bug, not a cosmetic
 * one. Two defects are guarded here:
 *
 *  1. Unchecking every permission box sent no `permissions` key at all, and
 *     the controller guarded the sync with `$request->has('permissions')`.
 *     Revoking the last remaining permission was therefore impossible - the
 *     stale rows stayed in `role_permission` and the form reported success.
 *  2. The slug was regenerated from the name on every update, so renaming a
 *     role could collide with another role's slug and abort the save with an
 *     unhandled UniqueConstraintViolationException (HTTP 500) instead of a
 *     validation message. The edit form has no slug field, so the slug is
 *     now only derived on create.
 */
class AdminRoleEditFormTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'role-super-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        foreach (['roles.view', 'roles.create', 'roles.update'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'roles',
            ])->getKey());
        }

        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_unchecking_every_permission_revokes_them_all(): void
    {
        $admin = $this->superAdmin();

        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $view = Permission::create([
            'name' => 'products.view',
            'slug' => 'products-view',
            'group_name' => 'products',
        ]);

        $role->permissions()->attach($view->getKey());
        $this->assertSame(1, $role->permissions()->count());

        // A form with every box unchecked submits no `permissions` key at all.
        $response = $this->actingAs($admin, 'admin')->put(route('admin.roles.update', $role), [
            'name' => 'Editor',
            'description' => 'No access at all now.',
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');

        $this->assertSame(
            0,
            $role->fresh()->permissions()->count(),
            'Revoking the final permission must detach it; the stale pivot row survived.'
        );
    }

    public function test_unchecking_a_subset_of_permissions_revokes_only_those(): void
    {
        $admin = $this->superAdmin();

        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $view = Permission::create([
            'name' => 'products.view',
            'slug' => 'products-view',
            'group_name' => 'products',
        ]);
        $create = Permission::create([
            'name' => 'products.create',
            'slug' => 'products-create',
            'group_name' => 'products',
        ]);

        $role->permissions()->attach([$view->getKey(), $create->getKey()]);

        $this->actingAs($admin, 'admin')->put(route('admin.roles.update', $role), [
            'name' => 'Editor',
            'permissions' => [$view->getKey()],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertSame([$view->getKey()], $role->fresh()->permissions()->pluck('_id')->all());
    }

    public function test_renaming_a_role_to_a_colliding_name_does_not_500(): void
    {
        $admin = $this->superAdmin();

        Role::create(['name' => 'Content Manager', 'slug' => 'content-manager']);
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        // "Content Manager" and "content-manager" slug identically. The unique
        // name rule passes (the names differ), so only the slug could collide -
        // it used to abort the request with a 500.
        $response = $this->actingAs($admin, 'admin')->put(route('admin.roles.update', $role), [
            'name' => 'Content Manager',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame('editor', $role->fresh()->slug, 'A rejected rename must not have touched the slug.');
    }

    public function test_renaming_a_role_leaves_its_slug_untouched(): void
    {
        $admin = $this->superAdmin();

        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        $this->actingAs($admin, 'admin')->put(route('admin.roles.update', $role), [
            'name' => 'Content Editor',
        ])->assertRedirect(route('admin.roles.index'));

        // The edit form has no slug input, so the slug is not a user-editable
        // field. Deriving it again from the name meant a rename could land on
        // another role's slug and abort the save with a 500.
        $this->assertSame('editor', Role::where('name', 'Content Editor')->value('slug'));
    }

    public function test_creating_a_role_whose_slug_collides_is_a_form_error_not_a_500(): void
    {
        $admin = $this->superAdmin();

        Role::create(['name' => 'Content Manager', 'slug' => 'content-manager']);

        // "Content-Manager" is a different name but slugifies to the same
        // value, so the unique name rule passes and only the slug collides.
        $response = $this->actingAs($admin, 'admin')->post(route('admin.roles.store'), [
            'name' => 'Content-Manager',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('roles', ['name' => 'Content-Manager']);
    }

    public function test_edit_form_loads_the_ckeditor_asset_before_replacing_the_textarea(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->getContent();

        // CKEDITOR.replace() threw "CKEDITOR is not defined" on this form: the
        // replace call was pushed but the library was never loaded.
        $this->assertStringContainsString('vendor/ckeditor/ckeditor.js', $html);

        $this->assertLessThan(
            strpos($html, 'CKEDITOR.replace'),
            strpos($html, 'vendor/ckeditor/ckeditor.js'),
            'The CKEditor library must be loaded before the inline replace() call runs.'
        );
    }

    public function test_edit_form_pre_checks_the_permissions_already_granted_to_the_role(): void
    {
        $admin = $this->superAdmin();

        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $granted = Permission::create([
            'name' => 'products.view',
            'slug' => 'products-view',
            'group_name' => 'products',
        ]);
        $denied = Permission::create([
            'name' => 'products.create',
            'slug' => 'products-create',
            'group_name' => 'products',
        ]);

        $role->permissions()->attach($granted->getKey());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.preg_quote($granted->getKey(), '/').'"\s+checked/',
            $html,
            'A permission the role already holds must render checked.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="'.preg_quote($denied->getKey(), '/').'"\s+checked/',
            $html,
            'A permission the role does not hold must not render checked.'
        );
    }
}
