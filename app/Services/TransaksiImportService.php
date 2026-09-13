<?php

namespace App\Services;

use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\KanalPembayaran;
use App\Models\PeriodePembukuan;
use App\Models\RelasiBank;
use App\Models\Transaksi;
use App\Models\TransaksiRincian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransaksiImportService
{
    /**
     * Import data CSV transaksi lengkap beserta rincian objek penerimaan.
     *
     * @param  string  $filePath
     * @param  int|null  $defaultBankId
     * @param  string  $delimiter
     * @return array
     */
    public function import(string $filePath, ?int $defaultBankId = null, string $delimiter = ','): array
    {
        if (! file_exists($filePath)) {
            return [
                'success' => false,
                'transaksi_count' => 0,
                'rincian_count' => 0,
                'errors' => ["File tidak ditemukan: {$filePath}"],
            ];
        }

        $file = fopen($filePath, 'r');
        if (! $file) {
            return [
                'success' => false,
                'transaksi_count' => 0,
                'rincian_count' => 0,
                'errors' => ["Gagal membuka file: {$filePath}"],
            ];
        }

        $headerRow = fgetcsv($file, 0, $delimiter);
        if (! $headerRow) {
            fclose($file);
            return [
                'success' => false,
                'transaksi_count' => 0,
                'rincian_count' => 0,
                'errors' => ["File CSV kosong atau tidak valid."],
            ];
        }

        // Auto detect delimiter titik koma (;)
        if (count($headerRow) === 1 && str_contains($headerRow[0], ';') && $delimiter !== ';') {
            rewind($file);
            $delimiter = ';';
            $headerRow = fgetcsv($file, 0, $delimiter);
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), $headerRow);

        $allBanks    = RelasiBank::all();
        $bankMapKode = $allBanks->keyBy(fn ($b) => strtoupper(trim($b->kode_bank)));
        $bankMapNama = $allBanks->keyBy(fn ($b) => strtoupper(trim($b->nama_bank)));
        $bankMapId   = $allBanks->keyBy('id');

        $allKanals    = KanalPembayaran::all();
        $kanalMapKode = $allKanals->keyBy(fn ($k) => strtoupper(trim($k->kode)));
        $kanalMapNama = $allKanals->keyBy(fn ($k) => strtoupper(trim($k->nama)));

        $allInstansi    = Instansi::all();
        $instansiMapKode = $allInstansi->keyBy(fn ($i) => strtoupper(trim($i->kode_instansi)));
        $instansiMapNama = $allInstansi->keyBy(fn ($i) => strtoupper(trim($i->nama_instansi)));

        $rekeningMap = JenisPenerimaan::all()->keyBy('kode');

        $grouped = [];
        $rowNumber = 1;
        $uniqueIndex = 1;

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (empty(array_filter($row))) {
                continue;
            }

            if (count($row) !== count($headers)) {
                continue;
            }

            $record = array_combine($headers, $row);
            $refKey = ! empty($record['no_referensi'])
                ? trim($record['no_referensi'])
                : '__row_' . ($uniqueIndex++);

            $grouped[$refKey][] = [
                'row_number' => $rowNumber,
                'data'       => $record,
            ];
        }
        fclose($file);

        $totalTrans = 0;
        $totalRincian = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($grouped as $refKey => $items) {
                $firstItem = $items[0]['data'];
                $firstRowNumber = $items[0]['row_number'];

                $bankInput = strtoupper(trim($firstItem['kode_bank'] ?? $firstItem['bank'] ?? ''));
                $bank = null;
                if ($bankInput !== '') {
                    $bank = $bankMapKode->get($bankInput) ?? $bankMapNama->get($bankInput);
                } elseif ($defaultBankId) {
                    $bank = $bankMapId->get($defaultBankId);
                }

                if (! $bank) {
                    $errors[] = "Baris {$firstRowNumber}: Bank '{$bankInput}' tidak ditemukan di sistem.";
                    continue;
                }

                $tanggalRaw = trim($firstItem['tanggal_transaksi'] ?? $firstItem['tanggal'] ?? '');
                $timestamp = strtotime($tanggalRaw);
                if (! $timestamp) {
                    $errors[] = "Baris {$firstRowNumber}: Format tanggal '{$tanggalRaw}' tidak valid (gunakan YYYY-MM-DD).";
                    continue;
                }
                $tanggal = date('Y-m-d', $timestamp);
                $tahun = (int) date('Y', $timestamp);
                $bulan = (int) date('m', $timestamp);

                $periode = PeriodePembukuan::where('relasi_bank_id', $bank->id)
                    ->where('tahun', $tahun)
                    ->where('bulan', $bulan)
                    ->first();

                $kodeKanal = strtoupper(trim($firstItem['kode_kanal'] ?? $firstItem['kanal'] ?? ''));
                $kanal = $kodeKanal !== '' ? ($kanalMapKode->get($kodeKanal) ?? $kanalMapNama->get($kodeKanal)) : null;

                $kodeInstansi = strtoupper(trim($firstItem['kode_instansi'] ?? $firstItem['instansi'] ?? ''));
                $instansi = $kodeInstansi !== '' ? ($instansiMapKode->get($kodeInstansi) ?? $instansiMapNama->get($kodeInstansi)) : null;

                $nominalInduk = (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', trim($firstItem['nominal'] ?? '0')));
                $tipeMutasi = strtoupper(trim($firstItem['tipe_mutasi'] ?? 'D'));
                if (! in_array($tipeMutasi, ['D', 'K'])) {
                    $tipeMutasi = 'D';
                }

                $status = trim($firstItem['status'] ?? 'Posted');
                if (! in_array($status, ['Raw', 'Verified', 'Validated', 'Posted'])) {
                    $status = 'Posted';
                }

                $deskripsi = trim($firstItem['deskripsi'] ?? '');

                $transaksi = Transaksi::create([
                    'relasi_bank_id'        => $bank->id,
                    'tanggal_transaksi'     => $tanggal,
                    'deskripsi'             => $deskripsi,
                    'nominal'               => $nominalInduk,
                    'tipe_mutasi'           => $tipeMutasi,
                    'kanal_pembayaran_id'   => $kanal?->id,
                    'instansi_id'           => $instansi?->id,
                    'periode_pembukuan_id'  => $periode?->id,
                    'status'                => $status,
                ]);

                $totalTrans++;

                foreach ($items as $item) {
                    $rData = $item['data'];
                    $rRow = $item['row_number'];

                    $kodeRek = trim($rData['kode_rekening'] ?? $rData['kode_jenis_penerimaan'] ?? '');
                    if ($kodeRek === '') {
                        continue;
                    }

                    $jenis = $rekeningMap->get($kodeRek);
                    if (! $jenis) {
                        $errors[] = "Baris {$rRow}: Kode rekening '{$kodeRek}' tidak ditemukan pada jenis penerimaan.";
                        continue;
                    }

                    $nomRincianRaw = trim($rData['nominal_rincian'] ?? '');
                    $nomRincian = $nomRincianRaw !== ''
                        ? (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $nomRincianRaw))
                        : $nominalInduk;

                    TransaksiRincian::create([
                        'transaksi_id'        => $transaksi->id,
                        'jenis_penerimaan_id' => $jenis->id,
                        'nominal'             => $nomRincian,
                    ]);

                    $totalRincian++;
                }
            }

            DB::commit();

            return [
                'success'         => true,
                'transaksi_count' => $totalTrans,
                'rincian_count'   => $totalRincian,
                'errors'          => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Import Transaksi Lengkap failed: " . $e->getMessage());
            return [
                'success'         => false,
                'transaksi_count' => 0,
                'rincian_count'   => 0,
                'errors'          => ["Gagal impor: " . $e->getMessage()],
            ];
        }
    }
}
