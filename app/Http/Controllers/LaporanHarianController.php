<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RelasiBank;
use App\Models\Transaksi;
use App\Models\Instansi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LaporanHarianController extends Controller
{
    public function downloadPdf(int|string $tenant, Request $request)
    {
        $activeTenant = RelasiBank::findOrFail($tenant);

        $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'instansi_id' => 'nullable|integer',
        ]);

        $tanggalMulai = $request->query('tanggal_mulai');
        $tanggalSelesai = $request->query('tanggal_selesai');
        $instansiId = $request->query('instansi_id');

        $instansi = $instansiId ? Instansi::find($instansiId) : null;
        $namaInstansi = $instansi ? $instansi->nama_instansi : 'Semua Instansi';

        // Query transactions for tenant and date range without filtering by status (all statuses)
        $query = Transaksi::with(['rincian.jenisPenerimaan', 'kanalPembayaran'])
            ->where('relasi_bank_id', $activeTenant->id)
            ->whereDate('tanggal_transaksi', '>=', $tanggalMulai)
            ->whereDate('tanggal_transaksi', '<=', $tanggalSelesai)
            ->orderBy('tanggal_transaksi', 'asc')
            ->orderBy('id', 'asc');

        if ($instansiId) {
            $query->where('instansi_id', $instansiId);
        }

        $transaksiList = $query->get();
        $totalNominal = $transaksiList->sum('nominal');

        // Pivot Table Data: Row = Jenis Penerimaan, Col = Kanal Pembayaran
        $allKanals = [];
        $pivotRows = [];
        $kanalTotals = [];
        $grandTotalPivot = 0;

        foreach ($transaksiList as $t) {
            $namaKanal = $t->kanalPembayaran?->nama ?? 'Belum Teridentifikasi';

            if (!in_array($namaKanal, $allKanals)) {
                $allKanals[] = $namaKanal;
            }

            if (!isset($kanalTotals[$namaKanal])) {
                $kanalTotals[$namaKanal] = 0;
            }

            if ($t->rincian->isNotEmpty()) {
                foreach ($t->rincian as $r) {
                    $namaJenis = $r->jenisPenerimaan?->nama ?? 'Tanpa Jenis Penerimaan';
                    $nom = (float) $r->nominal;

                    if (!isset($pivotRows[$namaJenis])) {
                        $pivotRows[$namaJenis] = [
                            'nama' => $namaJenis,
                            'total' => 0,
                            'kanals' => [],
                        ];
                    }

                    $pivotRows[$namaJenis]['total'] += $nom;
                    $pivotRows[$namaJenis]['kanals'][$namaKanal] = ($pivotRows[$namaJenis]['kanals'][$namaKanal] ?? 0) + $nom;
                    $kanalTotals[$namaKanal] += $nom;
                    $grandTotalPivot += $nom;
                }
            } else {
                $namaJenis = 'Belum Dirinci';
                $nom = (float) $t->nominal;

                if (!isset($pivotRows[$namaJenis])) {
                    $pivotRows[$namaJenis] = [
                        'nama' => $namaJenis,
                        'total' => 0,
                        'kanals' => [],
                    ];
                }

                $pivotRows[$namaJenis]['total'] += $nom;
                $pivotRows[$namaJenis]['kanals'][$namaKanal] = ($pivotRows[$namaJenis]['kanals'][$namaKanal] ?? 0) + $nom;
                $kanalTotals[$namaKanal] += $nom;
                $grandTotalPivot += $nom;
            }
        }

        sort($allKanals);

        $pdf = Pdf::loadView('reports.laporan-harian', [
            'tenant' => $activeTenant,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'namaInstansi' => $namaInstansi,
            'transaksiList' => $transaksiList,
            'totalNominal' => $totalNominal,
            'allKanals' => $allKanals,
            'pivotRows' => array_values($pivotRows),
            'kanalTotals' => $kanalTotals,
            'grandTotalPivot' => $grandTotalPivot,
        ])->setPaper('a4', 'landscape');

        $filename = $tanggalMulai === $tanggalSelesai
            ? "laporan_harian_{$tanggalMulai}.pdf"
            : "laporan_harian_{$tanggalMulai}_to_{$tanggalSelesai}.pdf";

        return $pdf->download($filename);
    }
}