<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use App\Jobs\ProcessCsvImportJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);
});

test('ProcessCsvImportJob can be dispatched', function () {
    Queue::fake();

    ProcessCsvImportJob::dispatch('temp-csv/test_import.csv', $this->tenant->id);

    Queue::assertPushed(ProcessCsvImportJob::class);
});

test('ProcessCsvImportJob parses and imports CSV correctly', function () {
    // 1. Prepare fake CSV content
    $csvContent = "Tanggal,Keterangan,Jumlah,Mutasi\n" .
                  "2026-06-01,Transfer Masuk Pajak Hotel,1250000,Kredit\n" .
                  "2026-06-02,Pembayaran Retribusi Parkir,750000,K\n" .
                  "2026-06-04,Biaya Administrasi Bank,15000.50,Debit\n";

    $tempPath = 'temp-csv/test_import.csv';
    Storage::disk('local')->put($tempPath, $csvContent);

    // Assert file exists before running the job
    expect(Storage::disk('local')->exists($tempPath))->toBeTrue();

    // 2. Execute job synchronously
    $job = new ProcessCsvImportJob($tempPath, $this->tenant->id);
    $job->handle();

    // 3. Assert transactions are imported
    $transactions = Transaksi::where('relasi_bank_id', $this->tenant->id)->get();
    expect($transactions)->toHaveCount(3);

    // Row 1 Assertions
    $t1 = $transactions->first(fn ($t) => $t->deskripsi === 'Transfer Masuk Pajak Hotel');
    expect($t1)->not->toBeNull();
    expect($t1->tanggal_transaksi)->toBe('2026-06-01');
    expect((float)$t1->nominal)->toBe(1250000.0);
    expect($t1->tipe_mutasi)->toBe('K');
    expect($t1->status)->toBe('Raw');

    // Row 2 Assertions
    $t2 = $transactions->first(fn ($t) => $t->deskripsi === 'Pembayaran Retribusi Parkir');
    expect($t2)->not->toBeNull();
    expect($t2->tanggal_transaksi)->toBe('2026-06-02');
    expect((float)$t2->nominal)->toBe(750000.0);
    expect($t2->tipe_mutasi)->toBe('K');

    // Row 3 Assertions
    $t3 = $transactions->first(fn ($t) => $t->deskripsi === 'Biaya Administrasi Bank');
    expect($t3)->not->toBeNull();
    expect($t3->tanggal_transaksi)->toBe('2026-06-04');
    expect((float)$t3->nominal)->toBe(15000.50);
    expect($t3->tipe_mutasi)->toBe('D');

    // 4. Assert temp CSV file is deleted
    expect(Storage::disk('local')->exists($tempPath))->toBeFalse();
});
