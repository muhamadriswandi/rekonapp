<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles
    $this->operatorRole = Role::create(['name' => 'Operator']);
    $this->supervisorRole = Role::create(['name' => 'Supervisor']);

    // Seed permissions
    $permissions = [
        'ViewAny:Transaksi',
        'View:Transaksi',
        'Create:Transaksi',
        'Update:Transaksi',
        'Delete:Transaksi',
        'DeleteAny:Transaksi',
    ];
    foreach ($permissions as $permission) {
        \Spatie\Permission\Models\Permission::create(['name' => $permission]);
    }
    $this->operatorRole->givePermissionTo($permissions);
    $this->supervisorRole->givePermissionTo(['ViewAny:Transaksi', 'View:Transaksi']);

    // Seed tenant
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);

    // Create Operator user
    $this->operator = User::create([
        'name' => 'Operator User',
        'email' => 'operator@test.com',
        'password' => bcrypt('password')
    ]);
    $this->operator->assignRole($this->operatorRole);

    // Create Supervisor user
    $this->supervisor = User::create([
        'name' => 'Supervisor User',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password')
    ]);
    $this->supervisor->assignRole($this->supervisorRole);
});

test('operator has full access to transaksi policy actions', function () {
    $transaksi = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'nominal' => 1000,
        'status' => 'Raw'
    ]);

    expect($this->operator->can('viewAny', Transaksi::class))->toBeTrue();
    expect($this->operator->can('view', $transaksi))->toBeTrue();
    expect($this->operator->can('create', Transaksi::class))->toBeTrue();
    expect($this->operator->can('update', $transaksi))->toBeTrue();
    expect($this->operator->can('delete', $transaksi))->toBeTrue();
    expect($this->operator->can('uploadCsv', Transaksi::class))->toBeTrue();
    expect($this->operator->can('rincian', $transaksi))->toBeTrue();
    expect($this->operator->can('validate', Transaksi::class))->toBeTrue();
});

test('supervisor has read-only access and cannot perform writing or custom actions', function () {
    $transaksi = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'nominal' => 1000,
        'status' => 'Raw'
    ]);

    expect($this->supervisor->can('viewAny', Transaksi::class))->toBeTrue();
    expect($this->supervisor->can('view', $transaksi))->toBeTrue();

    // Restricted actions
    expect($this->supervisor->can('create', Transaksi::class))->toBeFalse();
    expect($this->supervisor->can('update', $transaksi))->toBeFalse();
    expect($this->supervisor->can('delete', $transaksi))->toBeFalse();
    expect($this->supervisor->can('uploadCsv', Transaksi::class))->toBeFalse();
    expect($this->supervisor->can('rincian', $transaksi))->toBeFalse();
    expect($this->supervisor->can('validate', Transaksi::class))->toBeFalse();
});
