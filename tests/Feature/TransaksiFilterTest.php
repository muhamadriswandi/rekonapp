<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use App\Filament\Resources\TransaksiResource\Pages\ListTransaksis;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Create Tenant (RelasiBank)
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);

    // 2. Create roles and authenticate User
    $operatorRole = Role::create(['name' => 'Operator']);
    $this->user = User::create([
        'name' => 'Operator User',
        'email' => 'operator@test.com',
        'password' => bcrypt('password')
    ]);
    $this->user->assignRole($operatorRole);
    
    $this->actingAs($this->user);

    // Set panel, tenant, and active year session context after authentication
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    session(['active_year' => 2026]);

    // 3. Create transactions
    // T1: June, status Raw
    $this->tJuneRaw = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 10000,
        'status' => 'Raw',
    ]);

    // T2: June, status Validated
    $this->tJuneValidated = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 20000,
        'status' => 'Validated',
    ]);

    // T3: July, status Raw
    $this->tJulyRaw = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-07-01',
        'nominal' => 30000,
        'status' => 'Raw',
    ]);
});

test('can filter transactions using month tabs and status dropdown', function () {
    // Default load: Should see all records
    Livewire::test(ListTransaksis::class)
        ->assertCanSeeTableRecords([
            $this->tJuneRaw,
            $this->tJuneValidated,
            $this->tJulyRaw,
        ])
        // 2. Filter by tab "Juni"
        ->set('activeTab', 'juni')
        ->assertCanSeeTableRecords([
            $this->tJuneRaw,
            $this->tJuneValidated,
        ])
        ->assertCanNotSeeTableRecords([
            $this->tJulyRaw,
        ])
        // 3. Keep tab "Juni", filter status to "Validated"
        ->filterTable('status', 'Validated')
        ->assertCanSeeTableRecords([
            $this->tJuneValidated,
        ])
        ->assertCanNotSeeTableRecords([
            $this->tJuneRaw,
            $this->tJulyRaw,
        ])
        // 4. Change tab to "Juli", status "Validated" should find nothing
        ->set('activeTab', 'juli')
        ->assertCanNotSeeTableRecords([
            $this->tJuneRaw,
            $this->tJuneValidated,
            $this->tJulyRaw,
        ])
        // 5. Keep tab "Juli", reset status filter
        ->filterTable('status', null)
        ->assertCanSeeTableRecords([
            $this->tJulyRaw,
        ])
        ->assertCanNotSeeTableRecords([
            $this->tJuneRaw,
            $this->tJuneValidated,
        ]);
});

test('login page has a year select component and sets it in session', function () {
    auth()->logout();

    Livewire::test(\App\Filament\Pages\Auth\CustomLogin::class)
        ->set('data.email', $this->user->email)
        ->set('data.password', 'password')
        ->set('data.tahun', '2027')
        ->call('authenticate')
        ->assertHasNoErrors();
        
    expect(session('active_year'))->toBe(2027);
});
