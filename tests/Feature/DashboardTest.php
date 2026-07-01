<?php

use App\Models\User;
use App\Models\Transaksi;
use App\Models\RelasiBank;
use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\TransaksiRincian;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\RevenueSummaryWidget;
use Filament\Facades\Filament;
use Filament\Tables\Table;
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
    $this->instansiA = Instansi::create([
        'kode_instansi' => 'INST_A',
        'nama_instansi' => 'Dinas Kesehatan'
    ]);

    $this->instansiB = Instansi::create([
        'kode_instansi' => 'INST_B',
        'nama_instansi' => 'Dinas Perhubungan'
    ]);

    // 3. Create parent JenisPenerimaan
    $this->pajakParent = JenisPenerimaan::create([
        'kode' => 'PAJAK_DAERAH',
        'nama' => 'Pajak Daerah'
    ]);

    $this->retribusiParent = JenisPenerimaan::create([
        'kode' => 'RETRIBUSI_DAERAH',
        'nama' => 'Retribusi Daerah'
    ]);

    $this->pendapatanParent = JenisPenerimaan::create([
        'kode' => 'PENDAPATAN_LAINNYA',
        'nama' => 'Pendapatan Lainnya'
    ]);

    // 4. Create child JenisPenerimaan
    $this->pajakHotel = JenisPenerimaan::create([
        'parent_id' => $this->pajakParent->id,
        'kode' => 'PAJAK_HOTEL',
        'nama' => 'Pajak Hotel'
    ]);

    $this->retribusiParkir = JenisPenerimaan::create([
        'parent_id' => $this->retribusiParent->id,
        'kode' => 'RETRIBUSI_PARKIR',
        'nama' => 'Retribusi Parkir'
    ]);

    $this->penerimaanLain = JenisPenerimaan::create([
        'parent_id' => $this->pendapatanParent->id,
        'kode' => 'PENERIMAAN_LAIN',
        'nama' => 'Penerimaan Lain-Lain'
    ]);

    // 5. Seed Transactions for Bank A
    // T1: Instansi A, Month 6 (June), Validated, 10000, Pajak Hotel
    $t1 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansiA->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 10000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t1->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 10000
    ]);

    // T2: Instansi A, Month 6 (June), Validated, 5000, Retribusi Parkir
    $t2 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansiA->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 5000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t2->id,
        'jenis_penerimaan_id' => $this->retribusiParkir->id,
        'nominal' => 5000
    ]);

    // T3: Instansi A, Month 7 (July), Validated, 8000, Pajak Hotel
    $t3 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansiA->id,
        'tanggal_transaksi' => '2026-07-05',
        'nominal' => 8000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t3->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 8000
    ]);

    // T4: Instansi B, Month 6 (June), Raw, 12000, Penerimaan Lain-Lain
    $t4 = Transaksi::create([
        'relasi_bank_id' => $this->bankA->id,
        'instansi_id' => $this->instansiB->id,
        'tanggal_transaksi' => '2026-06-20',
        'nominal' => 12000,
        'tipe_mutasi' => 'K',
        'status' => 'Raw'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t4->id,
        'jenis_penerimaan_id' => $this->penerimaanLain->id,
        'nominal' => 12000
    ]);

    // 6. Seed Transactions for Bank B (should be ignored when viewing Bank A)
    $t5 = Transaksi::create([
        'relasi_bank_id' => $this->bankB->id,
        'instansi_id' => $this->instansiA->id,
        'tanggal_transaksi' => '2026-06-10',
        'nominal' => 99000,
        'tipe_mutasi' => 'K',
        'status' => 'Validated'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t5->id,
        'jenis_penerimaan_id' => $this->pajakHotel->id,
        'nominal' => 99000
    ]);

    $t6 = Transaksi::create([
        'relasi_bank_id' => $this->bankB->id,
        'instansi_id' => $this->instansiA->id,
        'tanggal_transaksi' => '2026-06-15',
        'nominal' => 45000,
        'tipe_mutasi' => 'K',
        'status' => 'Raw'
    ]);
    TransaksiRincian::create([
        'transaksi_id' => $t6->id,
        'jenis_penerimaan_id' => $this->retribusiParkir->id,
        'nominal' => 45000
    ]);
    // Create user for tenant authentication context
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@test.com',
        'password' => bcrypt('password')
    ]);
});

test('StatsOverviewWidget displays metrics correctly scoped to active tenant', function () {
    // 1. Setup Filament context for Bank A
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->bankA);

    // 2. Invoke StatsOverviewWidget getStats
    $widget = new StatsOverviewWidget();
    $reflector = new ReflectionClass(StatsOverviewWidget::class);
    $method = $reflector->getMethod('getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    // 3. Assert stats array contents
    expect($stats)->toHaveCount(3);

    $validatedStat = $stats[0];
    $rawStat = $stats[1];
    $postedStat = $stats[2];

    expect($validatedStat->getLabel())->toBe('Total Tervalidasi');
    expect($validatedStat->getValue())->toBe('Rp 23.000,00'); // 10000 + 5000 + 8000
    expect($validatedStat->getDescription())->toBe('3 Transaksi berstatus Validated');

    expect($rawStat->getLabel())->toBe('Transaksi Raw (Butuh Tindakan)');
    expect($rawStat->getValue())->toBe('1 Transaksi');
    expect($rawStat->getDescription())->toBe('Total nominal: Rp 12.000,00');

    expect($postedStat->getLabel())->toBe('Total Posting');
    expect($postedStat->getValue())->toBe('Rp 0,00');
    expect($postedStat->getDescription())->toBe('0 Transaksi berstatus Posted');
});

test('RevenueSummaryWidget table query aggregates data correctly scoped to active tenant', function () {
    // 1. Setup Filament context for Bank A
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->bankA);

    // 2. Instantiate widget and resolve table
    $widget = new RevenueSummaryWidget();
    $table = $widget->table(Table::make($widget));
    $query = $table->getQuery();
    $results = $query->get();

    // 3. Assert grouped results for Bank A
    // Row 1: Dinas Kesehatan (instansiA), June (Month 6) -> Pajak=10000, Retribusi=5000, Pendapatan=0
    // Row 2: Dinas Kesehatan (instansiA), July (Month 7) -> Pajak=8000, Retribusi=0, Pendapatan=0
    // Row 3: Dinas Perhubungan (instansiB), June (Month 6) -> Pajak=0, Retribusi=0, Pendapatan=12000
    expect($results)->toHaveCount(3);

    // Assert row 1 (Dinas Kesehatan, Month 6)
    $row1 = $results->first(fn ($item) => $item->nama_instansi === 'Dinas Kesehatan' && $item->bulan == 6);
    expect($row1)->not->toBeNull();
    expect((float)$row1->total_pajak_daerah)->toBe(10000.0);
    expect((float)$row1->total_retribusi_daerah)->toBe(5000.0);
    expect((float)$row1->total_pendapatan_lainnya)->toBe(0.0);

    // Assert row 2 (Dinas Kesehatan, Month 7)
    $row2 = $results->first(fn ($item) => $item->nama_instansi === 'Dinas Kesehatan' && $item->bulan == 7);
    expect($row2)->not->toBeNull();
    expect((float)$row2->total_pajak_daerah)->toBe(8000.0);
    expect((float)$row2->total_retribusi_daerah)->toBe(0.0);
    expect((float)$row2->total_pendapatan_lainnya)->toBe(0.0);

    // Assert row 3 (Dinas Perhubungan, Month 6)
    $row3 = $results->first(fn ($item) => $item->nama_instansi === 'Dinas Perhubungan' && $item->bulan == 6);
    expect($row3)->not->toBeNull();
    expect((float)$row3->total_pajak_daerah)->toBe(0.0);
    expect((float)$row3->total_retribusi_daerah)->toBe(0.0);
    expect((float)$row3->total_pendapatan_lainnya)->toBe(12000.0);
});
