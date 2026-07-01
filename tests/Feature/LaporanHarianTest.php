<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use App\Models\Instansi;
use App\Models\KanalPembayaran;
use App\Models\JenisPenerimaan;
use App\Models\TransaksiRincian;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Create two banks (tenants)
    $this->bankA = RelasiBank::create([
        'kode_bank' => 'BANK_A',
        'nama_bank' => 'Bank A'
    ]);

    $this->bankB = RelasiBank::create([
        'kode_bank' => 'BANK_B',
        'nama_bank' => 'Bank B'
    ]);

    // 2. Create Instansi
    $this->instansi = Instansi::create([
        'kode_instansi' => 'INST_A',
        'nama_instansi' => 'Dinas Kesehatan'
    ]);

    // 3. Create JenisPenerimaan
    $this->pajakParent = JenisPenerimaan::create([
        'kode' => 'PAJAK_DAERAH',
        'nama' => 'Pajak Daerah'
    ]);
    $this->pajakHotel = JenisPenerimaan::create([
        'parent_id' => $this->pajakParent->id,
        'kode' => 'PAJAK_HOTEL',
        'nama' => 'Pajak Hotel'
    ]);

    // 4. Create KanalPembayaran
    $this->kanal = KanalPembayaran::create([
        'kode' => 'QRIS',
        'nama' => 'QRIS'
    ]);

    // 5. Seed Transactions for Bank A
    // T1: Validated
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 15000,
        'tipe_mutasi' => 'K',
        'kanal_pembayaran_id' => $this->kanal->id,
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 15000
    ]);

    // T2: Posted
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 5000,
        'tipe_mutasi' => 'K',
        'kanal_pembayaran_id' => $this->kanal->id,
        'status' => 'Posted'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 5000
    ]);

    // T3: Raw (Should be ignored)
    $t3 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 12000,
        'tipe_mutasi' => 'K',
        'kanal_pembayaran_id' => $this->kanal->id,
        'status' => 'Raw'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t3->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 12000
    ]);

    // T4: Validated in another date (Should be ignored when query is only 2026-06-16)
    $t4 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-17',
        'nominal' => 20000,
        'tipe_mutasi' => 'K',
        'kanal_pembayaran_id' => $this->kanal->id,
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t4->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 20000
    ]);

    // T5: Validated in another tenant (Should be ignored)
    $t5 = Transaksi::create([
        'relasi_bank_id' => $this->bankB->id,
        'instansi_id' => $this->instansi->id,
        'tanggal_transaksi' => '2026-06-16',
        'nominal' => 45000,
        'tipe_mutasi' => 'K',
        'kanal_pembayaran_id' => $this->kanal->id,
        'status' => 'Validated'
    ]);

    // Create and authenticate User
    $this->user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password')
    ]);
});

test('authenticated user can download daily report PDF', function () {
    $this->actingAs($this->user);

    // Get Panel URL route name for reports.laporan-harian with same start/end date
    $url = route('filament.admin.reports.laporan-harian', [
        'tenant' => $this->bankA->id,
        'tanggal_mulai' => '2026-06-16',
        'tanggal_selesai' => '2026-06-16',
    ]);

    $response = $this->get($url);
    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
    
    // Check that header attachment name contains correct date
    $response->assertHeader('content-disposition', 'attachment; filename=laporan_harian_2026-06-16.pdf');
});

test('authenticated user can download report PDF with date range', function () {
    $this->actingAs($this->user);

    // Get Panel URL route name with a range of 2026-06-16 to 2026-06-17
    $url = route('filament.admin.reports.laporan-harian', [
        'tenant' => $this->bankA->id,
        'tanggal_mulai' => '2026-06-16',
        'tanggal_selesai' => '2026-06-17',
    ]);

    $response = $this->get($url);
    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
    
    // Check that header attachment name contains correct date range
    $response->assertHeader('content-disposition', 'attachment; filename=laporan_harian_2026-06-16_to_2026-06-17.pdf');
});
