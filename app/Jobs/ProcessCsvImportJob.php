<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Transaksi;

class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, int $tenantId)
    {
        $this->filePath = $filePath;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $path = Storage::disk('local')->path($this->filePath);

        if (!file_exists($path)) {
            Log::error("CSV import job failed: File not found at {$path}");
            return;
        }

        $file = fopen($path, 'r');
        $delimiter = ',';

        // Parse header row
        $headers = null;
        if (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (count($row) === 1 && str_contains($row[0], ';')) {
                rewind($file);
                $delimiter = ';';
                $row = fgetcsv($file, 0, $delimiter);
            }

            $headers = array_map(function ($value) {
                return strtolower(trim($value));
            }, $row);
        }

        // Map columns
        $colTanggal = null;
        $colDeskripsi = null;
        $colNominal = null;
        $colTipe = null;

        if ($headers) {
            foreach ($headers as $index => $header) {
                if (str_contains($header, 'tanggal') || str_contains($header, 'date') || str_contains($header, 'tgl')) {
                    $colTanggal = $index;
                } elseif (str_contains($header, 'deskripsi') || str_contains($header, 'description') || str_contains($header, 'keterangan') || str_contains($header, 'ket') || str_contains($header, 'memo')) {
                    $colDeskripsi = $index;
                } elseif (str_contains($header, 'nominal') || str_contains($header, 'amount') || str_contains($header, 'jumlah') || str_contains($header, 'nilai') || str_contains($header, 'value')) {
                    $colNominal = $index;
                } elseif (str_contains($header, 'tipe') || str_contains($header, 'type') || str_contains($header, 'mutasi') || str_contains($header, 'd_k') || str_contains($header, 'db_cr')) {
                    $colTipe = $index;
                }
            }
        }

        $insertedCount = 0;

        // Parse data rows (Bypass Validation / Fault Tolerance)
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            try {
                $tanggalRaw = ($colTanggal !== null && isset($row[$colTanggal])) ? trim($row[$colTanggal]) : null;
                $deskripsi = ($colDeskripsi !== null && isset($row[$colDeskripsi])) ? trim($row[$colDeskripsi]) : null;
                $nominalRaw = ($colNominal !== null && isset($row[$colNominal])) ? trim($row[$colNominal]) : '0';
                $tipeRaw = ($colTipe !== null && isset($row[$colTipe])) ? trim($row[$colTipe]) : null;

                // Safe Date Parsing
                $tanggal = null;
                if ($tanggalRaw) {
                    $timestamp = strtotime($tanggalRaw);
                    if ($timestamp !== false) {
                        $tanggal = date('Y-m-d', $timestamp);
                    }
                }

                // Safe Nominal Cleaning
                $nominalClean = preg_replace('/[^\d,.-]/', '', $nominalRaw);
                if (str_contains($nominalClean, ',') && str_contains($nominalClean, '.')) {
                    $nominalClean = str_replace('.', '', $nominalClean);
                    $nominalClean = str_replace(',', '.', $nominalClean);
                } elseif (str_contains($nominalClean, ',')) {
                    $parts = explode(',', $nominalClean);
                    if (count($parts) === 2 && strlen($parts[1]) === 2) {
                        $nominalClean = str_replace(',', '.', $nominalClean);
                    } else {
                        $nominalClean = str_replace(',', '', $nominalClean);
                    }
                }
                $nominal = (float) $nominalClean;

                // Safe Mutation Type Parsing
                $tipe_mutasi = null;
                if ($tipeRaw) {
                    $tipeLower = strtolower($tipeRaw);
                    if (str_starts_with($tipeLower, 'd') || str_contains($tipeLower, 'debit') || str_contains($tipeLower, 'debet')) {
                        $tipe_mutasi = 'D';
                    } elseif (str_starts_with($tipeLower, 'k') || str_contains($tipeLower, 'kredit') || str_contains($tipeLower, 'credit')) {
                        $tipe_mutasi = 'K';
                    }
                }

                // Write raw record
                Transaksi::create([
                    'relasi_bank_id' => $this->tenantId,
                    'tanggal_transaksi' => $tanggal,
                    'deskripsi' => $deskripsi,
                    'nominal' => $nominal,
                    'tipe_mutasi' => $tipe_mutasi,
                    'status' => 'Raw', // Default Raw
                ]);

                $insertedCount++;
            } catch (\Exception $e) {
                Log::warning("Row import failed in ProcessCsvImportJob: " . $e->getMessage());
            }
        }

        fclose($file);
        Storage::disk('local')->delete($this->filePath);

        Log::info("ProcessCsvImportJob completed: Imported {$insertedCount} records for Bank ID {$this->tenantId}");
    }
}
