<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Read through config(), not env(): a cached config skips .env loading
        // entirely, so env('ADMIN_PASSWORD') is null and the account would be
        // reset to a fallback password.
        //
        // blank(), not a null check: an empty `ADMIN_PASSWORD=` is the more
        // dangerous case, because it looks configured and sailed straight past
        // the old `! $password` guard outside local/testing while still
        // producing a known 'password' account.
        $password = config('admin.seed_password');

        if (blank($password)) {
            throw new \RuntimeException(
                'Refusing to seed: admin.seed_password is empty. Set ADMIN_PASSWORD in .env, then run '
                    .'`php artisan config:clear && php artisan config:cache` so the cached config picks it up. '
                    .'An admin account must never be created with a known fallback password.'
            );
        }

        // Validate strong password (minimum 12 characters)
        if (strlen($password) < 12) {
            throw new \RuntimeException(
                'Admin password must be at least 12 characters long.'
            );
        }

        // Ensure roles are seeded before trying to assign them
        $this->call([
            RoleSeeder::class,
        ]);

        // 'password' and 'email_verified_at' are not fillable and users.password
        // is NOT NULL, so updateOrCreate()/create() would INSERT without a
        // password and fail. Build (or load) the row, forceFill every column in
        // one go, and save once.
        // withTrashed(): a soft-deleted admin still owns the unique email, so a
        // plain lookup would miss it and the INSERT would hit the unique index.
        $admin = User::withTrashed()->firstOrNew(['email' => config('admin.seed_email')]);

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->forceFill([
            'name' => 'Admin',
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        $superAdminRole = Role::where('name', 'Super Admin')->first();

        if (! $superAdminRole) {
            throw new \RuntimeException('Super Admin role could not be created even after running RoleSeeder.');
        }

        $admin->roles()->syncWithoutDetaching([$superAdminRole->getKey()]);
    }
}
