<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $role = $request->query('role');
        $status = $request->query('status');

        $admins = User::query()
            ->with('roles')
            ->whereHas('roles')
            ->whereDoesntHave('customer');

        $users = $admins->clone()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when(is_string($role) && $role !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->when(in_array($status, ['active', 'inactive', 'unverified'], true), fn ($query) => $query
                ->where(fn ($q) => match ($status) {
                    'active' => $q->where('is_active', true),
                    'unverified' => $q->whereNull('email_verified_at'),
                    default => $q->where('is_active', false),
                }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roleOptions = Role::orderBy('name')->pluck('name');

        // Stats describe the whole back-office roster, not the filtered page.
        $stats = [
            'total' => (clone $admins)->count(),
            'active' => (clone $admins)->where('is_active', true)->count(),
            'unverified' => (clone $admins)->whereNull('email_verified_at')->count(),
            'roles' => $roleOptions->count(),
        ];

        return view('admin.user.index', compact('users', 'stats', 'roleOptions'));
    }

    public function create()
    {
        $roles = Role::all();

        return view('admin.user.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        if ($request->has('role')) {
            $this->authorizeRoleAssignment($request->input('role'), 'roles.create');
        }

        $validated = $request->validated();

        // 'password' is absent from User::$fillable, so it is split out of the
        // mass-assigned payload and written explicitly below.
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        // 'password' is not fillable and users.password is NOT NULL, so
        // create() would INSERT before the password was assigned. Build the
        // model, assign, save once. The 'hashed' cast runs on assignment.
        $user = new User($validated);

        if (filled($password)) {
            $user->password = $password;
        }

        $user->save();

        if ($request->has('role')) {
            $user->assignRole($request->input('role'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();

        return view('admin.user.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if ($request->has('role')) {
            $this->authorizeRoleAssignment($request->input('role'), 'roles.update', $user);
        }

        $validated = $request->validated();

        // 'password' is absent from User::$fillable on purpose, so it has to be
        // split out of the mass-assigned payload and written explicitly. The
        // 'hashed' cast does the hashing.
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        // Blank input arrives as null, not '' (ConvertEmptyStringsToNull), so
        // an untouched password field must not overwrite the stored one.
        $user->fill($validated);

        if (filled($password)) {
            $user->password = $password;
        }

        $user->save();

        if ($request->has('role')) {
            $user->syncRoles($request->input('role'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $admin = auth('admin')->user();

        // The index view already hides the Delete button on the actor's own
        // row, but the route is still reachable directly, so the guard belongs
        // here too.
        if ($admin instanceof User && $user->is($admin)) {
            abort(403, 'You cannot delete your own account.');
        }

        // A Super Admin account is the back-office's escape hatch. Deleting one
        // can lock every other administrator out, so it is refused outright.
        if ($user->roles()->whereIn('name', ['Super Admin', 'super_admin'])->exists()) {
            abort(403, 'Super Admin accounts cannot be deleted.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Authorise assigning $targetRole.
     *
     * Assigning a role is a privileged act and the previous check only refused the
     * literal "Super Admin" name, so any back-office user could grant themselves a
     * role holding permissions they did not have. An actor may now only assign a
     * role whose permissions they already hold, may not edit their own role, and
     * must hold the permission governing the action being performed.
     */
    protected function authorizeRoleAssignment(string $targetRole, string $permission, ?User $targetUser = null): void
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof User) {
            abort(403, 'You must be signed in to assign roles.');
        }

        if ($targetUser instanceof User && $targetUser->is($admin)) {
            abort(403, 'You cannot change your own role.');
        }

        $role = Role::where('name', $targetRole)->first();

        if (! $role instanceof Role) {
            abort(422, 'The selected role does not exist.');
        }

        if (! $admin->hasPermission($permission)) {
            abort(403, "You do not have the '{$permission}' permission.");
        }

        $heldPermissionIds = $admin->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $held): array => $held->permissions->pluck('_id')->all())
            ->unique()
            ->all();

        $grantedElsewhere = array_diff($role->permissions()->pluck('_id')->all(), $heldPermissionIds);

        if ($grantedElsewhere !== []) {
            abort(403, 'You cannot assign a role that grants permissions you do not hold.');
        }
    }
}
