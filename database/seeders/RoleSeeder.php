<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Buat semua role
        $superadminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $operatorRole   = Role::firstOrCreate(['name' => 'Operator']);
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor']);

        // Create/retrieve Operator user
        $operatorUser = User::firstOrCreate(
            ['email' => 'operator@rekonapp.com'],
            [
                'name'     => 'Operator User',
                'password' => bcrypt('password'),
            ]
        );
        $operatorUser->syncRoles([$operatorRole]);

        // Create/retrieve Supervisor user
        $supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor@rekonapp.com'],
            [
                'name'     => 'Supervisor User',
                'password' => bcrypt('password'),
            ]
        );
        $supervisorUser->syncRoles([$supervisorRole]);

        // Buat/ambil user riswandi29@gmail.com, reset password menjadi 'password'
        $adminUser = User::updateOrCreate(
            ['email' => 'riswandi29@gmail.com'],
            [
                'name'     => 'Riswandi Admin',
                'password' => bcrypt('password'),
            ]
        );
        $adminUser->syncRoles([$superadminRole]);

        // Buat/ambil user admin@gmail.com, reset password menjadi 'password'
        $generalAdmin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name'     => 'General Admin',
                'password' => bcrypt('password'),
            ]
        );
        $generalAdmin->syncRoles([$superadminRole]);
    }
}
