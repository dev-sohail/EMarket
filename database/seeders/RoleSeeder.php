<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Super Admin', 'Admin', 'Editor', 'Moderator'];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(
                ['slug' => Str::slug($roleName)],
                ['name' => $roleName]
            );

            $role->is_system = $roleName === 'Super Admin';
            $role->save();
        }
    }
}
