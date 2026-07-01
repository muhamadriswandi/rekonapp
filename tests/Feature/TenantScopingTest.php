<?php

use App\Models\User;
use App\Models\Instansi;
use App\Models\RelasiBank;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user restricted to an instansi can only see and access tenants of that instansi', function () {
    // 1. Create Instansi
    $healthInstansi = Instansi::create([
        'kode_instansi' => 'HEALTH',
        'nama_instansi' => 'Dinas Kesehatan'
    ]);

    $eduInstansi = Instansi::create([
        'kode_instansi' => 'EDU',
        'nama_instansi' => 'Dinas Pendidikan'
    ]);

    // 2. Create Tenants (RelasiBank)
    $tenantHealth = RelasiBank::create([
        'kode_bank' => 'BANK_HEALTH',
        'nama_bank' => 'Rekening Kesehatan'
    ]);
    $tenantHealth->instansi()->attach($healthInstansi->id);

    $tenantEdu = RelasiBank::create([
        'kode_bank' => 'BANK_EDU',
        'nama_bank' => 'Rekening Pendidikan'
    ]);
    $tenantEdu->instansi()->attach($eduInstansi->id);

    $tenantGlobal = RelasiBank::create([
        'kode_bank' => 'BANK_GLOBAL',
        'nama_bank' => 'Rekening Global'
    ]);

    // 3. Create User assigned to Dinas Kesehatan
    $restrictedUser = User::create([
        'name' => 'Health User',
        'email' => 'health@test.com',
        'password' => bcrypt('password'),
        'instansi_id' => $healthInstansi->id
    ]);

    // 4. Assert scoping
    $panel = Mockery::mock(Filament\Panel::class);
    $tenants = $restrictedUser->getTenants($panel);
    expect($tenants)->toHaveCount(1);
    expect($tenants->first()->id)->toBe($tenantHealth->id);

    expect($restrictedUser->canAccessTenant($tenantHealth))->toBeTrue();
    expect($restrictedUser->canAccessTenant($tenantEdu))->toBeFalse();
    expect($restrictedUser->canAccessTenant($tenantGlobal))->toBeFalse();
});

test('global user (no instansi assigned) can see and access all tenants', function () {
    // 1. Create Instansi
    $healthInstansi = Instansi::create([
        'kode_instansi' => 'HEALTH',
        'nama_instansi' => 'Dinas Kesehatan'
    ]);

    // 2. Create Tenants
    $tenantHealth = RelasiBank::create([
        'kode_bank' => 'BANK_HEALTH',
        'nama_bank' => 'Rekening Kesehatan'
    ]);
    $tenantHealth->instansi()->attach($healthInstansi->id);

    $tenantGlobal = RelasiBank::create([
        'kode_bank' => 'BANK_GLOBAL',
        'nama_bank' => 'Rekening Global'
    ]);

    // 3. Create User with no instansi assigned (Global Admin)
    $globalUser = User::create([
        'name' => 'Global Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'instansi_id' => null
    ]);

    // 4. Assert scoping (sees all)
    $panel = Mockery::mock(Filament\Panel::class);
    $tenants = $globalUser->getTenants($panel);
    expect($tenants)->toHaveCount(2);

    expect($globalUser->canAccessTenant($tenantHealth))->toBeTrue();
    expect($globalUser->canAccessTenant($tenantGlobal))->toBeTrue();
});
