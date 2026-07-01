<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use App\Models\PeriodePembukuan;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles
    $this->operatorRole = Role::create(['name' => 'Operator']);
    $this->supervisorRole = Role::create(['name' => 'Supervisor']);
    $this->superAdminRole = Role::create(['name' => 'super_admin']);

    // Seed permissions
    $permissions = [
        'ViewAny:PeriodePembukuan',
        'View:PeriodePembukuan',
        'Create:PeriodePembukuan',
        'Update:PeriodePembukuan',
        'Delete:PeriodePembukuan',
        'DeleteAny:PeriodePembukuan',
    ];
    foreach ($permissions as $permission) {
        \Spatie\Permission\Models\Permission::create(['name' => $permission]);
    }
    // Give permissions to Supervisor
    $this->supervisorRole->givePermissionTo($permissions);

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

    // Create Super Admin user
    $this->superAdmin = User::create([
        'name' => 'Super Admin User',
        'email' => 'superadmin@test.com',
        'password' => bcrypt('password')
    ]);
    $this->superAdmin->assignRole($this->superAdminRole);
});

test('operator has no access to periode pembukuan policy actions', function () {
    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $this->tenant->id,
        'bulan' => 6,
        'tahun' => 2026,
        'status' => 'Open'
    ]);

    expect($this->operator->can('viewAny', PeriodePembukuan::class))->toBeFalse();
    expect($this->operator->can('view', $periode))->toBeFalse();
    expect($this->operator->can('create', PeriodePembukuan::class))->toBeFalse();
    expect($this->operator->can('update', $periode))->toBeFalse();
    expect($this->operator->can('delete', $periode))->toBeFalse();
    expect($this->operator->can('tutupBuku', $periode))->toBeFalse();
});

test('supervisor and super admin have full access to manage and close period', function () {
    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $this->tenant->id,
        'bulan' => 6,
        'tahun' => 2026,
        'status' => 'Open'
    ]);

    // Supervisor
    expect($this->supervisor->can('viewAny', PeriodePembukuan::class))->toBeTrue();
    expect($this->supervisor->can('view', $periode))->toBeTrue();
    expect($this->supervisor->can('create', PeriodePembukuan::class))->toBeTrue();
    expect($this->supervisor->can('update', $periode))->toBeTrue();
    expect($this->supervisor->can('delete', $periode))->toBeTrue();
    expect($this->supervisor->can('tutupBuku', $periode))->toBeTrue();

    // Super Admin
    expect($this->superAdmin->can('viewAny', PeriodePembukuan::class))->toBeTrue();
    expect($this->superAdmin->can('view', $periode))->toBeTrue();
    expect($this->superAdmin->can('create', PeriodePembukuan::class))->toBeTrue();
    expect($this->superAdmin->can('update', $periode))->toBeTrue();
    expect($this->superAdmin->can('delete', $periode))->toBeTrue();
    expect($this->superAdmin->can('tutupBuku', $periode))->toBeTrue();
});

test('tutup buku dan posting successfully calculates totals and updates transactions', function () {
    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $this->tenant->id,
        'bulan' => 6,
        'tahun' => 2026,
        'status' => 'Open'
    ]);

    // 1. Validated Debit Transaction (D)
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 15000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    // 2. Validated Credit Transaction (K)
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 10000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);

    // 3. Validated Debit Transaction (D)
    $t3 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-20',
        'nominal' => 5000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    // 4. Verified Transaction (should NOT be posted)
    $t4 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-25',
        'nominal' => 25000,
        'tipe_mutasi' => 'D',
        'status' => 'Verified'
    ]);

    // 5. Validated Transaction in another month (should NOT be posted)
    $t5 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-07-01',
        'nominal' => 30000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    // Trigger close book
    $periode->tutupBukuDanPosting();

    // Refresh model
    $periode->refresh();

    // Assert Period State
    expect($periode->status)->toBe('Closed');
    expect((float)$periode->total_debit)->toBe(20000.0); // 15000 + 5000
    expect((float)$periode->total_kredit)->toBe(10000.0); // 10000
    expect($periode->closed_at)->not->toBeNull();

    // Assert Transactions Status
    $t1->refresh();
    $t2->refresh();
    $t3->refresh();
    $t4->refresh();
    $t5->refresh();

    expect($t1->status)->toBe('Posted');
    expect($t1->periode_pembukuan_id)->toBe($periode->id);

    expect($t2->status)->toBe('Posted');
    expect($t2->periode_pembukuan_id)->toBe($periode->id);

    expect($t3->status)->toBe('Posted');
    expect($t3->periode_pembukuan_id)->toBe($periode->id);

    // Unaffected transactions
    expect($t4->status)->toBe('Verified');
    expect($t4->periode_pembukuan_id)->toBeNull();

    expect($t5->status)->toBe('Validated');
    expect($t5->periode_pembukuan_id)->toBeNull();
});
