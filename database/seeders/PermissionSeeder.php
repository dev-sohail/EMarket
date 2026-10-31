<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'products' => ['view', 'create', 'update', 'delete'],
            'categories' => ['view', 'create', 'update', 'delete'],
            'brands' => ['view', 'create', 'update', 'delete'],
            'attributes' => ['view', 'create', 'update', 'delete'],
            'orders' => ['view', 'update', 'cancel'],
            'customers' => ['view', 'update'],
            'inventory' => ['view', 'update'],
            'coupons' => ['view', 'create', 'update', 'delete'],
            'campaigns' => ['view', 'create', 'update', 'delete'],
            'flash_sales' => ['view', 'create', 'update', 'delete'],
            'returns' => ['view', 'update'],
            'reviews' => ['view', 'update', 'delete'],
            'invoices' => ['view', 'create'],
            'pages' => ['view', 'create', 'update', 'delete'],
            'banners' => ['view', 'create', 'update', 'delete'],
            'shipping' => ['view', 'update'],
            'tax' => ['view', 'update'],
            'settings' => ['view', 'update'],
            'users' => ['view', 'create', 'update', 'delete'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'reports' => ['view', 'export'],
            'audit_logs' => ['view'],
            'tags' => ['view', 'create', 'update', 'delete'],
            'faqs' => ['view', 'create', 'update', 'delete'],
            'enquiries' => ['view', 'update', 'delete'],
            'chat_shop' => ['view', 'update'],
            'affiliate' => ['view', 'create', 'update', 'delete', 'export'],
            'import_export' => ['view', 'import', 'export'],
            'notifications' => ['view', 'update', 'delete'],
            'analytics' => ['view', 'export'],
            'blogs' => ['view', 'create', 'update', 'delete'],
            'popups' => ['view', 'create', 'update', 'delete'],
            'payment_methods' => ['view', 'update'],
            'api_management' => ['view', 'create', 'update', 'delete'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::firstOrCreate(
                    ['slug' => Str::slug($name, '-')],
                    [
                        'name' => $name,
                        'group_name' => $module,
                    ]
                );
            }
        }
    }
}
