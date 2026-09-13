<?php

use App\Models\User;
use App\Models\RelasiBank;
use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->bankA = RelasiBank::create([
        'kode_bank' => 'BANK_A',
        'nama_bank' => 'Bank Jabar'
    ]);

    $this->bankB = RelasiBank::create([
        'kode_bank' => 'BANK_B',
        'nama_bank' => 'Bank BCA'
    ]);

    $this->instansi = Instansi::create([
        'kode_instansi' => 'BAPENDA',
        'nama_instansi' => 'Badan Pendapatan Daerah'
    ]);
    $this->bankA->instansi()->attach($this->instansi->id);
    $this->bankB->instansi()->attach($this->instansi->id);

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

    // Create 3-level hierarchy for JenisPenerimaan
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
    $this->level3 = JenisPenerimaan::create([
        'kode' => '4.1.01.01',
        'nama' => 'Hotel Bintang Lima',
        'parent_id' => $this->level2->id,
    ]);
});

test('laporan konsolidasi pdf generates successfully with 4-column hierarchical roll-up layout', function () {
    // Level 3 item 1: 4.1.01.01 (Hotel Bintang Lima)
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 15000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3->id,
        'nominal' => 15000000
    ]);

    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->bankB->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-11',
        'nominal' => 5000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->level3->id,
        'nominal' => 5000000
    ]);

    // Create a second Level 3 under Level 2: 4.1.01.02 (Hotel Bintang Empat)
    $level3_2 = JenisPenerimaan::create([
        'kode' => '4.1.01.02',
        'nama' => 'Hotel Bintang Empat',
        'parent_id' => $this->level2->id,
    ]);
    $t3 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-12',
        'nominal' => 10000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t3->id,
        'jenis_penerimaan_id' => $level3_2->id,
        'nominal' => 10000000
    ]);

    // Test PDF response with tingkat=3 (default)
    $response = $this->get(route('filament.admin.reports.laporan-konsolidasi', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 3,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');

    // Test controller buildReportData directly to verify hierarchical roll-up
    $controller = app(\App\Http\Controllers\LaporanKonsolidasiController::class);
    $req = \Illuminate\Http\Request::create('/admin/reports/laporan-konsolidasi', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 3,
    ]);
    $reportData = $controller->buildReportData($req, [$this->bankA->id, $this->bankB->id]);

    expect($reportData['grand_total'])->toEqual(30000000);

    $rowsByKode = collect($reportData['rows'])->keyBy('kode');

    // Tingkat 1 (Pajak Daerah 4.1): sum of all TK 2 + TK 3 under it = 20M + 10M = 30M
    expect($rowsByKode->has('4.1'))->toBeTrue();
    expect($rowsByKode['4.1']['level'])->toEqual(1);
    expect($rowsByKode['4.1']['subtotal'])->toEqual(30000000);

    // Tingkat 2 (Pajak Hotel 4.1.01): sum of all TK 3 under it = 20M + 10M = 30M
    expect($rowsByKode->has('4.1.01'))->toBeTrue();
    expect($rowsByKode['4.1.01']['level'])->toEqual(2);
    expect($rowsByKode['4.1.01']['subtotal'])->toEqual(30000000);

    // Tingkat 3 (Hotel Bintang Lima 4.1.01.01): 15M + 5M = 20M
    expect($rowsByKode->has('4.1.01.01'))->toBeTrue();
    expect($rowsByKode['4.1.01.01']['level'])->toEqual(3);
    expect($rowsByKode['4.1.01.01']['subtotal'])->toEqual(20000000);
    expect(count($rowsByKode['4.1.01.01']['tenants']))->toEqual(2);

    // Tingkat 3 (Hotel Bintang Empat 4.1.01.02): 10M
    expect($rowsByKode->has('4.1.01.02'))->toBeTrue();
    expect($rowsByKode['4.1.01.02']['level'])->toEqual(3);
    expect($rowsByKode['4.1.01.02']['subtotal'])->toEqual(10000000);

    // Assert view renders with 1 single 'Kode Penerimaan' column header (4 total headers)
    $view = view('reports.laporan-konsolidasi', [
        'nama_instansi' => 'BAPENDA',
        'alamat_instansi' => '',
        'judul_laporan' => 'LAPORAN REKAPITULASI PENERIMAAN DAERAH',
        'sub_judul' => '',
        'periode_text' => 'Juni 2026',
        'tanggal_cetak' => '13 September 2026',
        'penandatangan' => '',
        'dari_bulan_num' => 6,
        'sampai_bulan_num' => 6,
        'tahun' => 2026,
        'tingkat' => 3,
        'rows' => $reportData['rows'],
        'grand_total' => $reportData['grand_total'],
    ])->render();

    expect($view)->toContain('<th style="width: 18%;">Kode Penerimaan</th>');
    expect($view)->toContain('<th style="width: 42%;">Nama Penerimaan</th>');
    expect($view)->toContain('<th style="width: 20%;">Tenant</th>');
    expect($view)->toContain('Jumlah (Rp)');
    expect($view)->not->toContain('Kode Tk. 1');
    expect($view)->not->toContain('Kode Tk. 2');
    expect($view)->not->toContain('Kode Tk. 3');
    expect($view)->toContain('PAJAK DAERAH');
    expect($view)->toContain('Pajak Hotel');
    expect($view)->toContain('Hotel Bintang Lima');
    expect($view)->toContain('Hotel Bintang Empat');
    expect($view)->toContain('Bank Jabar');
    expect($view)->toContain('Bank BCA');
});

test('laporan konsolidasi filters rows based on tingkat parameter', function () {
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 15000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3->id,
        'nominal' => 15000000
    ]);

    $controller = app(\App\Http\Controllers\LaporanKonsolidasiController::class);
    $bankIds = [$this->bankA->id, $this->bankB->id];

    // tingkat = 1: only Level 1
    $req1 = \Illuminate\Http\Request::create('/admin/reports/laporan-konsolidasi', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 1,
    ]);
    $data1 = $controller->buildReportData($req1, $bankIds);
    $levels1 = collect($data1['rows'])->pluck('level')->unique()->values()->all();
    expect($levels1)->toEqual([1]);

    // tingkat = 2: Level 1 and Level 2
    $req2 = \Illuminate\Http\Request::create('/admin/reports/laporan-konsolidasi', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 2,
    ]);
    $data2 = $controller->buildReportData($req2, $bankIds);
    $levels2 = collect($data2['rows'])->pluck('level')->unique()->values()->all();
    expect($levels2)->toEqual([1, 2]);

    // tingkat = 3: Level 1, Level 2, and Level 3
    $req3 = \Illuminate\Http\Request::create('/admin/reports/laporan-konsolidasi', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 3,
    ]);
    $data3 = $controller->buildReportData($req3, $bankIds);
    $levels3 = collect($data3['rows'])->pluck('level')->unique()->values()->all();
    expect($levels3)->toEqual([1, 2, 3]);
});

test('laporan konsolidasi excel generates successfully with 4 columns', function () {
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 10000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3->id,
        'nominal' => 10000000
    ]);

    $response = $this->get(route('filament.admin.reports.laporan-konsolidasi-excel', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 3,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('get laporan konsolidasi mcp tool executes cleanly with roll-up', function () {
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 15000000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->level3->id,
        'nominal' => 15000000
    ]);

    $tool = new \App\Mcp\Tools\GetLaporanKonsolidasiTool();
    $request = new \Laravel\Mcp\Request(['bulan' => 6, 'tahun' => 2026, 'tingkat' => 3]);
    $response = $tool->handle($request);

    expect($response)->toBeInstanceOf(\Laravel\Mcp\Response::class);
    $rawContent = (string) $response->content();
    expect($rawContent)->toContain('15000000');
    expect($rawContent)->toContain('Pajak Daerah');
});

test('laporan konsolidasi supports 5-level hierarchy roll-up where level 4 displays roll-up from level 5 child', function () {
    // Level 1: 4.1 (Pajak Daerah)
    // Level 2: 4.1.02 (Pajak Restoran)
    $lvl2 = JenisPenerimaan::create([
        'kode' => '4.1.02',
        'nama' => 'Pajak Restoran',
        'parent_id' => $this->level1->id,
    ]);

    // Level 3: 4.1.02.02 (Restoran Tertentu)
    $lvl3 = JenisPenerimaan::create([
        'kode' => '4.1.02.02',
        'nama' => 'Restoran Tertentu',
        'parent_id' => $lvl2->id,
    ]);

    // Level 4: 4.1.02.02.001 (Rumah Makan Padang)
    $lvl4 = JenisPenerimaan::create([
        'kode' => '4.1.02.02.001',
        'nama' => 'Rumah Makan Padang',
        'parent_id' => $lvl3->id,
    ]);

    // Level 5: 4.1.02.02.001.00001 (Meja Kasir 1)
    $lvl5 = JenisPenerimaan::create([
        'kode' => '4.1.02.02.001.00001',
        'nama' => 'Meja Kasir 1',
        'parent_id' => $lvl4->id,
    ]);

    // Input transaction on Level 5
    $tx = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 2500000,
        'tipe_mutasi' => 'D',
        'status' => 'Posted',
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $tx->id,
        'jenis_penerimaan_id' => $lvl5->id,
        'nominal' => 2500000,
    ]);

    $controller = app(\App\Http\Controllers\LaporanKonsolidasiController::class);
    $req = \Illuminate\Http\Request::create('/admin/reports/laporan-konsolidasi', 'GET', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]);
    $reportData = $controller->buildReportData($req, [$this->bankA->id, $this->bankB->id]);

    $rowsByKode = collect($reportData['rows'])->keyBy('kode');

    // Level 4 (4.1.02.02.001) MUST display the rolled-up amount from Level 5 child
    expect($rowsByKode->has('4.1.02.02.001'))->toBeTrue();
    expect($rowsByKode['4.1.02.02.001']['level'])->toEqual(4);
    expect($rowsByKode['4.1.02.02.001']['subtotal'])->toEqual(2500000);

    // Level 5 (4.1.02.02.001.00001) MUST display the amount
    expect($rowsByKode->has('4.1.02.02.001.00001'))->toBeTrue();
    expect($rowsByKode['4.1.02.02.001.00001']['level'])->toEqual(5);
    expect($rowsByKode['4.1.02.02.001.00001']['subtotal'])->toEqual(2500000);

    // Level 3, 2, 1 roll-up as well
    expect($rowsByKode['4.1.02.02']['subtotal'])->toEqual(2500000);
    expect($rowsByKode['4.1.02']['subtotal'])->toEqual(2500000);
    expect($rowsByKode['4.1']['subtotal'])->toEqual(2500000);

    // Test PDF response with tingkat=5
    $response = $this->get(route('filament.admin.reports.laporan-konsolidasi', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]));
    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');

    // Test Excel response with tingkat=5
    $responseExcel = $this->get(route('filament.admin.reports.laporan-konsolidasi-excel', [
        'dari_bulan' => 6,
        'sampai_bulan' => 6,
        'tahun' => 2026,
        'tingkat' => 5,
    ]));
    $responseExcel->assertStatus(200);
});
