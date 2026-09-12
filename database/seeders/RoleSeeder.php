<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    private const PERMISSIONS = [
        'view-products', 'create-products', 'update-products', 'delete-products',
        'view-categories', 'manage-categories',
        'view-warehouses', 'manage-warehouses',
        'view-stock', 'adjust-stock',
        'view-customers', 'manage-customers',
        'view-orders', 'create-orders', 'manage-orders', 'cancel-orders', 'refund-orders',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $staff = Role::firstOrCreate(['name' => 'Staff']);

        $admin->syncPermissions(self::PERMISSIONS);

        $manager->syncPermissions([
            'view-products', 'create-products', 'update-products', 'delete-products',
            'view-categories', 'manage-categories',
            'view-warehouses',
            'view-stock', 'adjust-stock',
            'view-customers', 'manage-customers',
            'view-orders', 'create-orders', 'manage-orders', 'cancel-orders',
        ]);

        $staff->syncPermissions([
            'view-products',
            'view-categories',
            'view-warehouses',
            'view-stock', 'adjust-stock',
            'view-customers',
            'view-orders', 'create-orders', 'manage-orders',
        ]);
    }
}
