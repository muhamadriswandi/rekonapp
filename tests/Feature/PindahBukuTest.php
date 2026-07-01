<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use App\Models\PindahBuku;
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
        'ViewAny:PindahBuku',
        'View:PindahBuku',
        'Create:PindahBuku',
        'Update:PindahBuku',
        'Delete:PindahBuku',
        'DeleteAny:PindahBuku',
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

test('operator has no access to pindah buku policy actions', function () {
    $pindah = PindahBuku::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-06-20',
        'status' => 'Open'
    ]);

    expect($this->operator->can('viewAny', PindahBuku::class))->toBeFalse();
    expect($this->operator->can('view', $pindah))->toBeFalse();
    expect($this->operator->can('create', PindahBuku::class))->toBeFalse();
    expect($this->operator->can('update', $pindah))->toBeFalse();
    expect($this->operator->can('delete', $pindah))->toBeFalse();
    expect($this->operator->can('tutupBuku', $pindah))->toBeFalse();
});

test('supervisor and super admin have full access to manage and close pindah buku', function () {
    $pindah = PindahBuku::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-06-20',
        'status' => 'Open'
    ]);

    // Supervisor
    expect($this->supervisor->can('viewAny', PindahBuku::class))->toBeTrue();
    expect($this->supervisor->can('view', $pindah))->toBeTrue();
    expect($this->supervisor->can('create', PindahBuku::class))->toBeTrue();
    expect($this->supervisor->can('update', $pindah))->toBeTrue();
    expect($this->supervisor->can('delete', $pindah))->toBeTrue();
    expect($this->supervisor->can('tutupBuku', $pindah))->toBeTrue();

    // Super Admin
    expect($this->superAdmin->can('viewAny', PindahBuku::class))->toBeTrue();
    expect($this->superAdmin->can('view', $pindah))->toBeTrue();
    expect($this->superAdmin->can('create', PindahBuku::class))->toBeTrue();
    expect($this->superAdmin->can('update', $pindah))->toBeTrue();
    expect($this->superAdmin->can('delete', $pindah))->toBeTrue();
    expect($this->superAdmin->can('tutupBuku', $pindah))->toBeTrue();
});

test('tutup buku dan posting successfully calculates totals and updates transactions for pindah buku', function () {
    $pindah = PindahBuku::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-06-20',
        'status' => 'Open'
    ]);

    // 1. Validated Debit Transaction (D) inside range
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 15000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    // 2. Validated Credit Transaction (K) inside range
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 10000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);

    // 3. Validated Debit Transaction (D) inside range
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
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 25000,
        'tipe_mutasi' => 'D',
        'status' => 'Verified'
    ]);

    // 5. Validated Transaction outside range (should NOT be posted)
    $t5 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-25',
        'nominal' => 30000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    // Associate the selected transactions with the PindahBuku
    $t1->update(['pindah_buku_id' => $pindah->id]);
    $t2->update(['pindah_buku_id' => $pindah->id]);
    $t3->update(['pindah_buku_id' => $pindah->id]);

    // Trigger close book
    $pindah->tutupBukuDanPosting();

    // Refresh model
    $pindah->refresh();

    // Assert Pindah Buku State
    expect($pindah->status)->toBe('Closed');
    expect((float)$pindah->total_debit)->toBe(20000.0); // 15000 + 5000
    expect((float)$pindah->total_kredit)->toBe(10000.0); // 10000
    expect($pindah->closed_at)->not->toBeNull();

    // Assert Transactions Status
    $t1->refresh();
    $t2->refresh();
    $t3->refresh();
    $t4->refresh();
    $t5->refresh();

    expect($t1->status)->toBe('Posted');
    expect($t1->pindah_buku_id)->toBe($pindah->id);

    expect($t2->status)->toBe('Posted');
    expect($t2->pindah_buku_id)->toBe($pindah->id);

    expect($t3->status)->toBe('Posted');
    expect($t3->pindah_buku_id)->toBe($pindah->id);

    // Unaffected transactions
    expect($t4->status)->toBe('Verified');
    expect($t4->pindah_buku_id)->toBeNull();

    expect($t5->status)->toBe('Validated');
    expect($t5->pindah_buku_id)->toBeNull();
});

test('can create pindah buku and associate selected transactions', function () {
    $this->actingAs($this->supervisor);
    
    // Set panel and tenant context
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    \Filament\Facades\Filament::setTenant($this->tenant);

    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 15000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated'
    ]);

    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 20000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);

    \Livewire\Livewire::test(\App\Filament\Resources\PindahBukuResource\Pages\CreatePindahBuku::class, [
        'tenant' => $this->tenant->id,
    ])
        ->fillForm([
            'tanggal_mulai' => '2026-06-10',
            'tanggal_selesai' => '2026-06-20',
            'status' => 'Open',
            'keterangan' => 'Test Pindah Buku',
            'selected_transaksi' => [$t1->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pindah = PindahBuku::firstWhere('keterangan', 'Test Pindah Buku');
    expect($pindah)->not->toBeNull();
    
    $t1->refresh();
    $t2->refresh();
    expect($t1->pindah_buku_id)->toBe($pindah->id);
    expect($t2->pindah_buku_id)->toBeNull();
});

test('can edit pindah buku and sync selected transactions', function () {
    $this->actingAs($this->supervisor);
    
    // Set panel and tenant context
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    \Filament\Facades\Filament::setTenant($this->tenant);

    $pindah = PindahBuku::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-06-20',
        'status' => 'Open',
        'keterangan' => 'Edit Pindah Buku'
    ]);

    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 15000,
        'tipe_mutasi' => 'D',
        'status' => 'Validated',
        'pindah_buku_id' => $pindah->id
    ]);

    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 20000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);

    \Livewire\Livewire::test(\App\Filament\Resources\PindahBukuResource\Pages\EditPindahBuku::class, [
        'record' => $pindah->getKey(),
        'tenant' => $this->tenant->id,
    ])
        ->fillForm([
            'selected_transaksi' => [$t2->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $t1->refresh();
    $t2->refresh();
    expect($t1->pindah_buku_id)->toBeNull();
    expect($t2->pindah_buku_id)->toBe($pindah->id);
});

