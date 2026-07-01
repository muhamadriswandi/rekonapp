<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RelasiBank;
use App\Models\Transaksi;
use Carbon\Carbon;

class PindahBukuDummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure there is a RelasiBank (tenant) to associate with
        $relasiBank = RelasiBank::firstOrCreate([
            'nama_bank' => 'Bank Dummy',
            'kode_bank' => 'DUMMY01',
        ], []);

        // Create 60 validated transactions between 28 Jun 2026 and 2 Jul 2026
        $startDate = Carbon::create(2026, 6, 28);
        $endDate = Carbon::create(2026, 7, 2);
        $dateRange = \Carbon\CarbonPeriod::create($startDate, $endDate);
        $dates = iterator_to_array($dateRange);
        $totalDates = count($dates);

        for ($i = 1; $i <= 60; $i++) {
            $date = $dates[$i % $totalDates]; // rotate through the dates
            Transaksi::create([
                'relasi_bank_id' => $relasiBank->id,
                'tanggal_transaksi' => $date->format('Y-m-d'),
                'deskripsi' => "Dummy transaction {$i}",
                'nominal' => rand(1000, 100000) / 100,
                'tipe_mutasi' => $i % 2 === 0 ? 'D' : 'K', // alternate debit/credit
                'status' => 'Validated',
                // other required fields can be set to defaults or null
            ]);
        }
    }
}
