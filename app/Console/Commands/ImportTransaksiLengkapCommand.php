<?php

namespace App\Console\Commands;

use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\KanalPembayaran;
use App\Models\PeriodePembukuan;
use App\Models\RelasiBank;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTransaksiLengkapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:transaksi-lengkap {file : Path file CSV yang akan diimpor} {--delimiter=, : Pemisah kolom CSV (, atau ;)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Impor data CSV lengkap ke tabel transaksi dan transaksi_rincian';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\TransaksiImportService $importService): int
    {
        $filePath = $this->argument('file');
        $delimiter = $this->option('delimiter') ?: ',';

        $this->info("Memulai proses impor data dari: {$filePath}");

        $result = $importService->import($filePath, null, $delimiter);

        if (! $result['success']) {
            foreach ($result['errors'] as $err) {
                $this->error($err);
            }
            return Command::FAILURE;
        }

        if (! empty($result['errors'])) {
            $this->warn("Terdapat catatan/peringatan pada beberapa baris:");
            foreach ($result['errors'] as $err) {
                $this->line(" - {$err}");
            }
        }

        $this->info("==========================================");
        $this->info("Impor selesai dengan sukses!");
        $this->info("Total Transaksi Induk Dibuat   : {$result['transaksi_count']}");
        $this->info("Total Rincian Transaksi Dibuat : {$result['rincian_count']}");
        $this->info("==========================================");

        return Command::SUCCESS;
    }
}
