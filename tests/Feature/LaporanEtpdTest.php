<?php

use App\Models\User;
use App\Models\RelasiBank;
use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\KanalPembayaran;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->bankA = RelasiBank::create([
        'kode_bank' => 'BANK_A',
        'nama_bank' => 'Bank Jabar'
    ]);

    $this->instansi = Instansi::create([
        'kode_instansi' => 'BAPENDA',
        'nama_instansi' => 'Badan Pendapatan Daerah'
    ]);
    $this->bankA->instansi()->attach($this->instansi->id);

    $role = Role::create(['name' => 'super_admin']);

    $this->user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'instansi_id' => $this->instansi->id,
    ]);
    $this->user->assignRole($role);
    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Payment channels
    $this->kanalQris = KanalPembayaran::create([
        'kode' => 'QRIS',
        'nama' => 'QRIS',
    ]);

    $this->kanalAtm = KanalPembayaran::create([
        'kode' => 'ATM',
        'nama' => 'ATM',
    ]);

    $this->kanalTeller = KanalPembayaran::create([
        'kode' => 'TELLER',
        'nama' => 'Teller',
    ]);

    // 3-level hierarchy
    // Level 1: 4.1 (Pajak Daerah)
    $this->level1 = JenisPenerimaan::create([
        'kode' => '4.1',
        'nama' => 'Pajak Daerah',
        'parent_id' => null,
    ]);

    // Level 2: 4.1.01 (Pajak Hotel)
    $this->level2 = JenisPenerimaan::create([
        'kode' => '4.1.01',
        'nama' => 'Pajak Hotel',
        'parent_id' => $this->level1->id,
    ]);

    // Level 3: 4.1.01.01 (Hotel Bintang Lima)
    $this->level3_1 = JenisPenerimaan::create([
        'kode' => '4.1.01.01',
        'nama' => 'Hotel Bintang Lima',
        'parent_id' => $this->level2->id,
    ]);

    // Level 3: 4.1.01.02 (Hotel Bintang Empat)
    $this->level3_2 = JenisPenerimaan::create([
        'kode' => '4.1.01.02',
        'nama' => 'Hotel Bintang Empat',
        'parent_id' => $this->level2->id,
    ]);
});

test('laporan etpd calculates pivot per channel and rolls up parent totals', function () {
    // Transaction 1: Level 3_1 via QRIS = 5.000.000
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'kanal_pembayaran_id' => $this->kanalQris->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 5000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3_1->id,
        'nominal' => 5000000
    ]);

    // Transaction 2: Level 3_1 via ATM = 3.000.000
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'kanal_pembayaran_id' => $this->kanalAtm->id,
        'tanggal_transaksi' => '2026-06-11',
        'nominal' => 3000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->level3_1->id,
        'nominal' => 3000000
    ]);

    // Transaction 3: Level 3_2 via Teller = 2.000.000
    $t3 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'kanal_pembayaran_id' => $this->kanalTeller->id,
        'tanggal_transaksi' => '2026-06-12',
        'nominal' => 2000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t3->id,
        'jenis_penerimaan_id' => $this->level3_2->id,
        'nominal' => 2000000
    ]);

    $controller = app(\App\Http\Controllers\LaporanEtpdController::class);
    $req = Request::create('/admin/reports/laporan-etpd', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]);
    $reportData = $controller->buildReportData($req, [$this->bankA->id]);

    expect($reportData['grand_total'])->toEqual(10000000);
    expect($reportData['grand_total_per_kanal'][(string)$this->kanalQris->id])->toEqual(5000000);
    expect($reportData['grand_total_per_kanal'][(string)$this->kanalAtm->id])->toEqual(3000000);
    expect($reportData['grand_total_per_kanal'][(string)$this->kanalTeller->id])->toEqual(2000000);

    $rowsByKode = collect($reportData['rows'])->keyBy('kode');

    // Level 1: 4.1 (Pajak Daerah) rolls up all channels = 10.000.000
    expect($rowsByKode->has('4.1'))->toBeTrue();
    expect($rowsByKode['4.1']['total'])->toEqual(10000000);
    expect($rowsByKode['4.1']['kanals'][(string)$this->kanalQris->id])->toEqual(5000000);
    expect($rowsByKode['4.1']['kanals'][(string)$this->kanalAtm->id])->toEqual(3000000);
    expect($rowsByKode['4.1']['kanals'][(string)$this->kanalTeller->id])->toEqual(2000000);

    // Level 2: 4.1.01 (Pajak Hotel) rolls up all children = 10.000.000
    expect($rowsByKode->has('4.1.01'))->toBeTrue();
    expect($rowsByKode['4.1.01']['total'])->toEqual(10000000);
    expect($rowsByKode['4.1.01']['kanals'][(string)$this->kanalQris->id])->toEqual(5000000);
    expect($rowsByKode['4.1.01']['kanals'][(string)$this->kanalAtm->id])->toEqual(3000000);
    expect($rowsByKode['4.1.01']['kanals'][(string)$this->kanalTeller->id])->toEqual(2000000);

    // Level 3_1: 4.1.01.01 (Hotel Bintang Lima) = QRIS 5M + ATM 3M = 8M
    expect($rowsByKode['4.1.01.01']['total'])->toEqual(8000000);
    expect($rowsByKode['4.1.01.01']['kanals'][(string)$this->kanalQris->id])->toEqual(5000000);
    expect($rowsByKode['4.1.01.01']['kanals'][(string)$this->kanalAtm->id])->toEqual(3000000);
    expect($rowsByKode['4.1.01.01']['kanals'][(string)$this->kanalTeller->id])->toEqual(0);

    // Level 3_2: 4.1.01.02 (Hotel Bintang Empat) = Teller 2M
    expect($rowsByKode['4.1.01.02']['total'])->toEqual(2000000);
    expect($rowsByKode['4.1.01.02']['kanals'][(string)$this->kanalTeller->id])->toEqual(2000000);
});

test('laporan etpd pdf generates successfully with pivot table channels', function () {
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'kanal_pembayaran_id' => $this->kanalQris->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 5000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3_1->id,
        'nominal' => 5000000
    ]);

    $response = $this->get(route('filament.admin.reports.laporan-etpd', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');

    // Test blade view output
    $controller = app(\App\Http\Controllers\LaporanEtpdController::class);
    $req = Request::create('/admin/reports/laporan-etpd', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]);
    $reportData = $controller->buildReportData($req, [$this->bankA->id]);

    $view = view('reports.laporan-etpd', [
        'nama_instansi' => 'BAPENDA',
        'alamat_instansi' => '',
        'judul_laporan' => 'LAPORAN REALISASI ETPD (KANAL PEMBAYARAN)',
        'sub_judul' => '',
        'periode_text' => 'Juni 2026',
        'tanggal_cetak' => '13 September 2026',
        'penandatangan' => '',
        'kanals' => $reportData['kanals'],
        'rows' => $reportData['rows'],
        'grand_total_per_kanal' => $reportData['grand_total_per_kanal'],
        'grand_total' => $reportData['grand_total'],
    ])->render();

    expect($view)->toContain('Kode Penerimaan');
    expect($view)->toContain('Nama Penerimaan');
    expect($view)->toContain('QRIS');
    expect($view)->toContain('ATM');
    expect($view)->toContain('Teller');
    expect($view)->toContain('Total (Rp)');
    expect($view)->toContain('GRAND TOTAL KESELURUHAN');
});

test('laporan etpd excel generates successfully with pivot table channels', function () {
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'kanal_pembayaran_id' => $this->kanalQris->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 5000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3_1->id,
        'nominal' => 5000000
    ]);

    $response = $this->get(route('filament.admin.reports.laporan-etpd-excel', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('laporan etpd page is accessible in filament admin panel', function () {
    \Livewire\Livewire::test(\App\Filament\Pages\LaporanEtpdPage::class)
        ->assertSuccessful()
        ->assertSee('Filter Periode')
        ->assertSee('Konfigurasi Header Laporan')
        ->assertSee('Cetak PDF')
        ->assertSee('Download Excel');
});
