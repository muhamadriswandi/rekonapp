<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use App\Models\RelasiBank;
use App\Models\JenisPenerimaan;
use App\Models\Instansi;
use App\Models\PeriodePembukuan;
use App\Models\PindahBuku;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);

    $this->instansi = Instansi::create([
        'kode_instansi' => 'INS01',
        'nama_instansi' => 'Badan Pendapatan Daerah'
    ]);
    $this->tenant->instansi()->attach($this->instansi->id);

    $role = Role::create(['name' => 'Supervisor']);
    $role->givePermissionTo([
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:PindahBuku']),
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View:PindahBuku']),
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'Create:PindahBuku']),
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'Update:PindahBuku']),
    ]);

    $this->user = User::create([
        'name' => 'Supervisor User',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password'),
        'instansi_id' => $this->instansi->id,
    ]);
    $this->user->assignRole($role);
    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    session(['active_year' => 2026]);

    $this->jenis = JenisPenerimaan::create([
        'kode' => 'JP01',
        'nama' => 'Pajak Daerah'
    ]);
});

test('pindah buku with periode pembukuan posts transactions and sets their periode_pembukuan_id', function () {
    // 1. Create a Periode Pembukuan for February 2026
    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $this->tenant->id,
        'bulan' => 2,
        'tahun' => 2026,
        'status' => 'Open',
    ]);

    // 2. Create Pindah Buku linked to the Periode Pembukuan
    $pindahBuku = PindahBuku::create([
        'relasi_bank_id' => $this->tenant->id,
        'periode_pembukuan_id' => $periode->id,
        'tanggal_mulai' => '2026-01-25',
        'tanggal_selesai' => '2026-02-05',
        'status' => 'Open',
        'keterangan' => 'Pindah Buku Cutoff Jan-Feb',
    ]);

    // 3. Create transactions with cut-off dates (e.g. 31 Jan and 1 Feb)
    // Both need rincian + instansi to be Validated
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-01-31', // Late January transaction
        'nominal' => 100000,
        'tipe_mutasi' => 'K',
        'pindah_buku_id' => $pindahBuku->id,
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 100000,
    ]);
    $t1->refresh();
    expect($t1->status)->toBe('Validated');

    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-02-01', // Early February transaction
        'nominal' => 200000,
        'tipe_mutasi' => 'K',
        'pindah_buku_id' => $pindahBuku->id,
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 200000,
    ]);
    $t2->refresh();
    expect($t2->status)->toBe('Validated');

    // 4. Close Pindah Buku & Posting
    $pindahBuku->tutupBukuDanPosting();

    // 5. Assert Pindah Buku state
    $pindahBuku->refresh();
    expect($pindahBuku->status)->toBe('Closed');
    expect((float) $pindahBuku->total_kredit)->toBe(300000.0);
    expect($pindahBuku->closed_at)->not->toBeNull();

    // 6. Assert Transactions are now Posted and assigned to February Periode Pembukuan
    $t1->refresh();
    $t2->refresh();
    expect($t1->status)->toBe('Posted');
    expect($t1->periode_pembukuan_id)->toBe($periode->id);

    expect($t2->status)->toBe('Posted');
    expect($t2->periode_pembukuan_id)->toBe($periode->id);

    // 7. Assert Periode Pembukuan total was recalculated
    $periode->refresh();
    expect((float) $periode->total_kredit)->toBe(300000.0);

    // 8. Re-open Pindah Buku and unpost
    $pindahBuku->bukaKembaliDanUnpost();
    $pindahBuku->refresh();
    expect($pindahBuku->status)->toBe('Open');
    expect($pindahBuku->closed_at)->toBeNull();

    $t1->refresh();
    $t2->refresh();
    expect($t1->status)->toBe('Validated');
    expect($t1->periode_pembukuan_id)->toBeNull();
    expect($t2->status)->toBe('Validated');
    expect($t2->periode_pembukuan_id)->toBeNull();

    $periode->refresh();
    expect((float) $periode->total_kredit)->toBe(0.0);
});

test('laporan penerimaan correctly fetches cutoff transactions by periode pembukuan', function () {
    // Create Periode Pembukuan for February 2026
    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $this->tenant->id,
        'bulan' => 2,
        'tahun' => 2026,
        'status' => 'Open',
    ]);

    // Transaction with physical date 2026-01-31, but booked in Periode Feb (bulan=2)
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'instansi_id' => $this->instansi->id,
        'periode_pembukuan_id' => $periode->id,
        'tanggal_transaksi' => '2026-01-31',
        'nominal' => 150000,
        'tipe_mutasi' => 'K',
        'status' => 'Posted',
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->jenis->id,
        'nominal' => 150000,
    ]);

    // Download PDF for February 2026 (dari_bulan=2, sampai_bulan=2, tahun=2026)
    $response = $this->get(route('filament.admin.reports.laporan-penerimaan', [
        'tenant' => $this->tenant->id,
        'dari_bulan' => 2,
        'sampai_bulan' => 2,
        'tahun' => 2026,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});
