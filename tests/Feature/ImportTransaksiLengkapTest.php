<?php

use App\Models\User;
use App\Models\RelasiBank;
use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\KanalPembayaran;
use App\Models\PeriodePembukuan;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('import transaksi lengkap command successfully imports csv data into transaksi and transaksi_rincian', function () {
    // 1. Setup master data
    $bank = RelasiBank::create([
        'kode_bank' => 'BANK_JABAR',
        'nama_bank' => 'Bank BJB',
    ]);

    $instansi = Instansi::create([
        'kode_instansi' => 'BAPENDA',
        'nama_instansi' => 'Badan Pendapatan Daerah',
    ]);

    $kanal = KanalPembayaran::create([
        'kode' => 'QRIS',
        'nama' => 'QRIS',
    ]);

    $parent = JenisPenerimaan::create([
        'kode' => '4.1',
        'nama' => 'Pajak Daerah',
        'parent_id' => null,
    ]);

    $child1 = JenisPenerimaan::create([
        'kode' => '4.1.01.01',
        'nama' => 'Hotel Bintang Lima',
        'parent_id' => $parent->id,
    ]);

    $child2 = JenisPenerimaan::create([
        'kode' => '4.1.01.02',
        'nama' => 'Hotel Bintang Empat',
        'parent_id' => $parent->id,
    ]);

    $periode = PeriodePembukuan::create([
        'relasi_bank_id' => $bank->id,
        'tahun'          => 2026,
        'bulan'          => 6,
        'status'         => 'Open',
    ]);

    // 2. Buat temporary CSV file
    $csvContent = implode("\n", [
        'no_referensi,kode_bank,tanggal_transaksi,deskripsi,nominal,tipe_mutasi,kode_kanal,kode_instansi,status,kode_rekening,nominal_rincian',
        'TRX-001,BANK_JABAR,2026-06-10,Penerimaan Hotel 5 Star,15000000,D,QRIS,BAPENDA,Posted,4.1.01.01,15000000',
        'TRX-002,BANK_JABAR,2026-06-11,Pembayaran Split 2 Hotel,25000000,D,QRIS,BAPENDA,Posted,4.1.01.01,15000000',
        'TRX-002,BANK_JABAR,2026-06-11,Pembayaran Split 2 Hotel,25000000,D,QRIS,BAPENDA,Posted,4.1.01.02,10000000',
    ]);

    $tempPath = storage_path('app/test_import.csv');
    File::put($tempPath, $csvContent);

    // 3. Jalankan artisan command
    $this->artisan('import:transaksi-lengkap', ['file' => $tempPath])
        ->expectsOutputToContain('Impor selesai dengan sukses!')
        ->assertExitCode(0);

    // 4. Verifikasi di database
    expect(Transaksi::count())->toEqual(2); // TRX-001 and TRX-002 (TRX-002 has 2 split rows)
    expect(TransaksiRincian::count())->toEqual(3);

    $t2 = Transaksi::where('nominal', 25000000)->first();
    expect($t2)->not->toBeNull();
    expect($t2->status)->toEqual('Posted');
    expect($t2->periode_pembukuan_id)->toEqual($periode->id);
    expect($t2->rincian()->count())->toEqual(2);

    // Cleanup
    if (File::exists($tempPath)) {
        File::delete($tempPath);
    }
});

test('action uploadCsvLengkap exists on ListTransaksis and processes csv file correctly', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $bank = RelasiBank::create([
        'kode_bank' => 'BANK_JABAR_UI',
        'nama_bank' => 'Bank BJB UI',
    ]);

    $instansi = Instansi::create([
        'kode_instansi' => 'BAPENDA_UI',
        'nama_instansi' => 'Badan Pendapatan Daerah UI',
    ]);

    $kanal = KanalPembayaran::create([
        'kode' => 'QRIS_UI',
        'nama' => 'QRIS UI',
    ]);

    $parent = JenisPenerimaan::create([
        'kode' => '4.1',
        'nama' => 'Pajak Daerah',
        'parent_id' => null,
    ]);

    $child = JenisPenerimaan::create([
        'kode' => '4.1.01.01',
        'nama' => 'Hotel Bintang Lima',
        'parent_id' => $parent->id,
    ]);

    PeriodePembukuan::create([
        'relasi_bank_id' => $bank->id,
        'tahun'          => 2026,
        'bulan'          => 6,
        'status'         => 'Open',
    ]);

    $operatorRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Operator']);
    $user = User::create([
        'name' => 'Operator CSV User',
        'email' => 'operator_csv_ui@test.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($operatorRole);

    $this->actingAs($user);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Filament\Facades\Filament::setTenant($bank);
    session(['active_year' => 2026]);

    $csvContent = implode("\n", [
        'no_referensi,kode_bank,tanggal_transaksi,deskripsi,nominal,tipe_mutasi,kode_kanal,kode_instansi,status,kode_rekening,nominal_rincian',
        'TRX-UI-001,BANK_JABAR_UI,2026-06-12,Penerimaan via Action Button,10000000,D,QRIS_UI,BAPENDA_UI,Posted,4.1.01.01,10000000',
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('transaksi_lengkap.csv', $csvContent);

    \Livewire\Livewire::test(\App\Filament\Resources\TransaksiResource\Pages\ListTransaksis::class)
        ->assertActionExists('uploadCsvLengkap')
        ->assertActionVisible('uploadCsvLengkap')
        ->callAction('uploadCsvLengkap', [
            'csv_file' => [$file],
        ])
        ->assertHasNoActionErrors();

    expect(Transaksi::where('deskripsi', 'Penerimaan via Action Button')->exists())->toBeTrue();
    expect(TransaksiRincian::where('nominal', 10000000)->exists())->toBeTrue();
});

