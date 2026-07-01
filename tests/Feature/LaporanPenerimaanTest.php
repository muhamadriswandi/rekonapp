<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use App\Models\RelasiBank;
use App\Models\JenisPenerimaan;
use App\Models\Instansi;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use App\Filament\Pages\LaporanPenerimaanPage;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);

    $role = Role::create(['name' => 'Supervisor']);
    
    $this->user = User::create([
        'name' => 'Supervisor User',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password')
    ]);
    $this->user->assignRole($role);
    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    session(['active_year' => 2026]);

    $this->jenis = JenisPenerimaan::create([
        'kode' => 'JP01',
        'nama' => 'Penerimaan Pajak'
    ]);
});

test('laporan penerimaan page shows only posted transaction details for the tenant in selected period', function () {
    // 1. Transaction under tenant, status Posted, in June
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 50000,
        'status' => 'Posted',
        'tipe_mutasi' => 'D'
    ]);
    $r1 = TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 50000
    ]);

    // 2. Transaction under tenant, status Validated (should NOT be in report)
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 30000,
        'status' => 'Validated',
        'tipe_mutasi' => 'D'
    ]);
    $r2 = TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 30000
    ]);

    // 3. Transaction under ANOTHER tenant, status Posted (should NOT be in report)
    $otherTenant = RelasiBank::create([
        'kode_bank' => 'OTHER',
        'nama_bank' => 'Other Bank'
    ]);
    $t3 = Transaksi::create([
        'relasi_bank_id' => $otherTenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 40000,
        'status' => 'Posted',
        'tipe_mutasi' => 'D'
    ]);
    $r3 = TransaksiRincian::create([
        'transaksi_id' => $t3->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 40000
    ]);

    Livewire::test(LaporanPenerimaanPage::class)
        ->fillForm([
            'dari_bulan' => 6,
            'sampai_bulan' => 6,
            'tahun' => 2026,
            'instansi_id' => null,
        ])
        ->assertCanSeeTableRecords([$r1])
        ->assertCanNotSeeTableRecords([$r2, $r3]);
});

test('laporan penerimaan page filters by instansi', function () {
    $instansiA = Instansi::create(['kode_instansi' => 'INST_A', 'nama_instansi' => 'Instansi A']);
    $instansiB = Instansi::create(['kode_instansi' => 'INST_B', 'nama_instansi' => 'Instansi B']);

    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 50000,
        'status' => 'Posted',
        'tipe_mutasi' => 'D',
        'instansi_id' => $instansiA->id
    ]);
    $r1 = TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 50000
    ]);

    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 30000,
        'status' => 'Posted',
        'tipe_mutasi' => 'D',
        'instansi_id' => $instansiB->id
    ]);
    $r2 = TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 30000
    ]);

    Livewire::test(LaporanPenerimaanPage::class)
        ->fillForm([
            'dari_bulan' => 6,
            'sampai_bulan' => 6,
            'tahun' => 2026,
            'instansi_id' => $instansiA->id,
        ])
        ->assertCanSeeTableRecords([$r1])
        ->assertCanNotSeeTableRecords([$r2]);
});

test('laporan penerimaan page redirects to pdf export route', function () {
    $instansi = Instansi::create(['id' => 123, 'kode_instansi' => 'TEST_PRINT', 'nama_instansi' => 'Test Print']);

    Livewire::test(LaporanPenerimaanPage::class)
        ->fillForm([
            'dari_bulan' => 6,
            'sampai_bulan' => 6,
            'tahun' => 2026,
            'instansi_id' => $instansi->id,
        ])
        ->call('cetakPdf')
        ->assertRedirect(route('filament.admin.reports.laporan-penerimaan', [
            'tenant' => $this->tenant->id,
            'dari_bulan' => 6,
            'sampai_bulan' => 6,
            'tahun' => 2026,
            'instansi_id' => $instansi->id,
        ]));
});

test('download pdf route returns pdf download response', function () {
    $this->actingAs($this->user);

    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 50000,
        'status' => 'Posted',
        'tipe_mutasi' => 'D'
    ]);
    $r1 = TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 50000
    ]);

    $response = $this->get(route('filament.admin.reports.laporan-penerimaan', [
        'tenant' => $this->tenant->id,
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename=laporan_penerimaan_6_to_6_2026.pdf');
});

