<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed a default bank (tenant)
        \App\Models\RelasiBank::firstOrCreate(
            ['kode_bank' => 'PDRD'],
            ['nama_bank' => 'Bank PDRD']
        );

        \App\Models\RelasiBank::firstOrCreate(
            ['kode_bank' => 'PBB'],
            ['nama_bank' => 'Bank PBB']
        );

        $this->call(RoleSeeder::class);
    }
}
