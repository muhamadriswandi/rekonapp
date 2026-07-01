<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $operatorRole = Role::firstOrCreate(['name' => 'Operator']);
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor']);

        // Create/retrieve Operator user
        $operatorUser = User::firstOrCreate(
            ['email' => 'operator@rekonapp.com'],
            [
                'name' => 'Operator User',
                'password' => bcrypt('password'),
            ]
        );
        $operatorUser->assignRole($operatorRole);

        // Create/retrieve Supervisor user
        $supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor@rekonapp.com'],
            [
                'name' => 'Supervisor User',
                'password' => bcrypt('password'),
            ]
        );
        $supervisorUser->assignRole($supervisorRole);

        // Assign Operator role to riswandi29@gmail.com if exists
        $adminUser = User::where('email', 'riswandi29@gmail.com')->first();
        if ($adminUser) {
            $adminUser->assignRole($operatorRole);
        }
    }
}
