<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->get();

        return view('admin.role.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::all()->groupBy('group_name');

        return view('admin.role.create', compact('permissions'));
    }

    public function store(StoreRoleRequest $request)
    {
        $this->authorizeRoleManagement();

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $role = Role::create($data);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions'));
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::all()->groupBy('group_name');

        return view('admin.role.edit', compact('role', 'permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorizeRoleManagement();

        $data = $request->validated();

        // The edit form has no slug field, so a submitted slug is always
        // absent. Regenerating it from the name here meant a rename could
        // collide with a different role's slug and abort the save with an
        // unhandled UniqueConstraintViolationException. The slug is derived
        // once on create and then left alone, so it stays a stable identifier.
        unset($data['slug']);

        $role->update($data);

        // A form with every box unchecked submits no `permissions` key at all,
        // because a browser omits unchecked checkboxes entirely. Guarding the
        // sync on `$request->has('permissions')` therefore skipped it in that
        // case, so the last permission could never be revoked: the stale pivot
        // row stayed in `role_permission` and the redirect still reported
        // success. The edit form always renders the permission panel, so its
        // absence from the request can only mean "nothing is checked" - sync
        // unconditionally and let an empty array detach everything.
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');

        $this->authorizeRoleManagement();

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    protected function authorizeRoleManagement(): void
    {
        $admin = auth('admin')->user();

        $isSuperAdmin = $admin->roles()->whereIn('name', ['Super Admin', 'super_admin'])->exists();

        if (! $isSuperAdmin) {
            abort(403, 'You do not have permission to manage roles.');
        }
    }
}
