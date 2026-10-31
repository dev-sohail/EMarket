<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('_id')->toArray();

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching($allPermissions);
        }

        $adminPermissions = Permission::whereNotIn('name', ['roles.delete'])->pluck('_id')->toArray();
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($adminPermissions);
        }

        $editorPermissions = Permission::whereIn('name', [
            'products.view', 'products.create', 'products.update',
            'categories.view', 'categories.create', 'categories.update',
            'brands.view', 'brands.create', 'brands.update',
            'pages.view', 'pages.create', 'pages.update',
            'banners.view', 'banners.create', 'banners.update',
            'reviews.view', 'reviews.update',
            'coupons.view', 'coupons.create', 'coupons.update',
            'campaigns.view', 'campaigns.create', 'campaigns.update',
            'flash_sales.view', 'flash_sales.create', 'flash_sales.update',
            'orders.view',
            'customers.view',
            'reports.view',
        ])->pluck('_id')->toArray();
        $editor = Role::where('name', 'Editor')->first();
        if ($editor) {
            $editor->permissions()->syncWithoutDetaching($editorPermissions);
        }

        $moderatorPermissions = Permission::whereIn('name', [
            'orders.view', 'orders.update', 'orders.cancel',
            'customers.view', 'customers.update',
            'returns.view', 'returns.update',
            'reviews.view', 'reviews.update', 'reviews.delete',
        ])->pluck('_id')->toArray();
        $moderator = Role::where('name', 'Moderator')->first();
        if ($moderator) {
            $moderator->permissions()->syncWithoutDetaching($moderatorPermissions);
        }
    }
}
