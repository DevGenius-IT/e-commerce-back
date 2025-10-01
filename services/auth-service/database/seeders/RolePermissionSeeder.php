<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Shared\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            
            // Product management
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            
            // Order management
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.cancel',
            
            // Customer service
            'support.view',
            'support.respond',
            'support.close',
            
            // Reports
            'reports.view',
            'reports.export',
            
            // Settings
            'settings.view',
            'settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create roles and assign permissions
        
        // Super Admin - has all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        if ($superAdmin->permissions->isEmpty()) {
            $superAdmin->givePermissionTo(Permission::all());
        }

        // Admin - has most permissions except critical settings
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        if ($admin->permissions->isEmpty()) {
            $admin->givePermissionTo([
                'users.view', 'users.create', 'users.update',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
                'support.view', 'support.respond', 'support.close',
                'reports.view', 'reports.export',
                'settings.view',
            ]);
        }

        // Manager - can manage products and orders
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);
        if ($manager->permissions->isEmpty()) {
            $manager->givePermissionTo([
                'products.view', 'products.create', 'products.update',
                'orders.view', 'orders.update',
                'reports.view',
            ]);
        }

        // Support - customer service role
        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'api']);
        if ($support->permissions->isEmpty()) {
            $support->givePermissionTo([
                'orders.view',
                'support.view', 'support.respond', 'support.close',
                'users.view',
            ]);
        }

        // Customer - basic customer role
        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'api']);
        if ($customer->permissions->isEmpty()) {
            $customer->givePermissionTo([
                'products.view',
                'orders.view', 'orders.create',
            ]);
        }

        // Merchant - can manage their own products
        $merchant = Role::firstOrCreate(['name' => 'merchant', 'guard_name' => 'api']);
        if ($merchant->permissions->isEmpty()) {
            $merchant->givePermissionTo([
                'products.view', 'products.create', 'products.update', 'products.delete',
                'orders.view',
                'reports.view',
            ]);
        }

        // Assign super-admin role to the first user (created in UserSeeder)
        $user = User::where('email', 'kylian@collect-verything.com')->first();
        if ($user) {
            // Specify the guard when assigning role
            $user->guard_name = 'api';
            $user->assignRole($superAdmin);
            $this->command->info('Super-admin role assigned to kylian@collect-verything.com');
        }

        $this->command->info('Roles and permissions created successfully!');
        $this->command->table(
            ['Role', 'Permissions Count'],
            Role::with('permissions')->get()->map(function ($role) {
                return [$role->name, $role->permissions->count()];
            })
        );
    }
}