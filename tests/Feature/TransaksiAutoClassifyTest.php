<?php

use App\Models\RelasiBank;
use App\Models\Transaksi;
use App\Models\KanalPembayaran;
use App\Models\JenisPenerimaan;
use App\Models\TransaksiRincian;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);
});

test('instant auto classification works when a new transaction is created', function () {
    // 1. Setup payment channel and revenue type with regex patterns
    $kanal = KanalPembayaran::create([
        'kode' => 'QRIS',
        'nama' => 'QRIS',
        'regex_pattern' => 'QRIS'
    ]);

    $jenis = JenisPenerimaan::create([
        'kode' => 'PAJAK_HOTEL',
        'nama' => 'Pajak Hotel',
        'regex_pattern' => 'Pajak Hotel'
    ]);

    // 2. Create a transaction that matches both patterns
    $transaksi = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-01',
        'deskripsi' => 'Setoran Pajak Hotel QRIS Bengkulu',
        'nominal' => 500000.00,
        'tipe_mutasi' => 'K',
        'status' => 'Raw',
        'kanal_pembayaran_id' => null,
    ]);

    // 3. Assert instant classification updated the transaction
    $transaksi->refresh();
    expect($transaksi->kanal_pembayaran_id)->toBe($kanal->id);
    expect($transaksi->status)->toBe('Verified');
    
    // Check that rincian was created
    expect($transaksi->rincian)->toHaveCount(1);
    $rincian = $transaksi->rincian->first();
    expect($rincian->jenis_penerimaan_id)->toBe($jenis->id);
    expect((float)$rincian->nominal)->toBe(500000.00);
});

test('retroactive KanalPembayaran classification matches existing transactions', function () {
    // 1. Create a transaction with no payment channel
    $transaksi1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-01',
        'deskripsi' => 'Pembayaran VA BANK BENGKULU',
        'nominal' => 250000.00,
        'tipe_mutasi' => 'K',
        'status' => 'Raw',
        'kanal_pembayaran_id' => null,
    ]);

    $transaksi2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-01',
        'deskripsi' => 'Pembayaran Tunai Manual',
        'nominal' => 150000.00,
        'tipe_mutasi' => 'K',
        'status' => 'Raw',
        'kanal_pembayaran_id' => null,
    ]);

    // 2. Save KanalPembayaran with regex pattern matching VA
    $kanal = KanalPembayaran::create([
        'kode' => 'VA',
        'nama' => 'Virtual Account',
        'regex_pattern' => '/VA/i'
    ]);

    // 3. Assert transaction matching VA got updated, other transaction remains null
    $transaksi1->refresh();
    $transaksi2->refresh();

    expect($transaksi1->kanal_pembayaran_id)->toBe($kanal->id);
    expect($transaksi2->kanal_pembayaran_id)->toBeNull();
});

test('retroactive JenisPenerimaan classification matches existing Raw transactions', function () {
    // 1. Create Raw transactions with no rincian
    $transaksi1 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-01',
        'deskripsi' => 'Pembayaran Retribusi Kebersihan Ruko',
        'nominal' => 100000.00,
        'tipe_mutasi' => 'K',
        'status' => 'Raw',
        'kanal_pembayaran_id' => null,
    ]);

    $transaksi2 = Transaksi::create([
        'relasi_bank_id' => $this->tenant->id,
        'tanggal_transaksi' => '2026-06-01',
        'deskripsi' => 'Pembayaran Retribusi Parkir',
        'nominal' => 50000.00,
        'tipe_mutasi' => 'K',
        'status' => 'Raw',
        'kanal_pembayaran_id' => null,
    ]);

    // 2. Save JenisPenerimaan with regex pattern matching Kebersihan
    $jenis = JenisPenerimaan::create([
        'kode' => 'RET_KEBERSIHAN',
        'nama' => 'Retribusi Kebersihan',
        'regex_pattern' => 'Kebersihan'
    ]);

    // 3. Assert matching transaction got verified and rincian created
    $transaksi1->refresh();
    $transaksi2->refresh();

    expect($transaksi1->status)->toBe('Verified');
    expect($transaksi1->rincian)->toHaveCount(1);
    expect($transaksi1->rincian->first()->jenis_penerimaan_id)->toBe($jenis->id);
    expect((float)$transaksi1->rincian->first()->nominal)->toBe(100000.00);

    expect($transaksi2->status)->toBe('Raw');
    expect($transaksi2->rincian)->toHaveCount(0);
});
