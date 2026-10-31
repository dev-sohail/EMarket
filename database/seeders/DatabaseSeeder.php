<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            AdminSeeder::class,
            PageSeeder::class,
            PaymentMethodSeeder::class,
            SettingSeeder::class,
            ApiManagementSeeder::class,
            FaqSeeder::class,
            BlogPostSeeder::class,
            ProductSeeder::class,
            ShippingSeeder::class,
        ]);
    }
}
