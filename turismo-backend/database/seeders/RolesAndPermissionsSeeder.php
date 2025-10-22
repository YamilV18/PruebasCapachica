<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            'user_create',
            'user_read',
            'user_update',
            'user_delete',
            'role_create',
            'role_read',
            'role_update',
            'role_delete',
            'permission_read',
            'permission_assign',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles y asignar permisos
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $operatorRole = Role::create(['name' => 'operator']);
        $operatorRole->givePermissionTo(['user_read', 'role_read', 'permission_read']);

        $userRole = Role::create(['name' => 'user']);
        
        // Crear usuario admin
        $admin = User::create([
            'name' => 'Admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@turismo.com',
            'password' => Hash::make('password123'),
        ]);
        
        $admin->assignRole('admin');
    }
}