<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RelasiBank;
use App\Models\Instansi;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanPenerimaanController extends Controller
{
    public function downloadPdf($tenant, Request $request)
    {
        $activeTenant = RelasiBank::findOrFail($tenant);

        $request->validate([
            'dari_bulan' => 'required|integer|between:1,12',
            'sampai_bulan' => 'required|integer|between:1,12|gte:dari_bulan',
            'tahun' => 'required|integer',
            'instansi_id' => 'nullable|integer',
        ]);

        $dariBulan = $request->query('dari_bulan');
        $sampaiBulan = $request->query('sampai_bulan');
        $tahun = $request->query('tahun');
        $instansiId = $request->query('instansi_id');

        $instansi = $instansiId ? Instansi::find($instansiId) : null;
        $namaInstansi = $instansi ? $instansi->nama_instansi : 'Konsolidasi (Semua Instansi)';

        // Fetch transaction details
        $query = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 'tr.transaksi_id', '=', 't.id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->leftJoin('instansi as i', 't.instansi_id', '=', 'i.id')
            ->select([
                't.tanggal_transaksi',
                'jp.nama as nama_penerimaan',
                'tr.nominal',
                'i.nama_instansi'
            ])
            ->where('t.relasi_bank_id', $activeTenant->id)
            ->where('t.status', 'Posted')
            ->whereYear('t.tanggal_transaksi', $tahun)
            ->whereMonth('t.tanggal_transaksi', '>=', $dariBulan)
            ->whereMonth('t.tanggal_transaksi', '<=', $sampaiBulan);

        if ($instansiId) {
            $query->where('t.instansi_id', $instansiId);
        }

        $data = $query->orderBy('jp.nama', 'asc')
            ->orderBy('t.tanggal_transaksi', 'asc')
            ->get();

        $groupedData = $data->groupBy('nama_penerimaan');

        $totalNominal = $data->sum('nominal');

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $pdf = Pdf::loadView('reports.laporan-penerimaan', [
            'tenant' => $activeTenant,
            'dariBulan' => $months[$dariBulan],
            'sampaiBulan' => $months[$sampaiBulan],
            'tahun' => $tahun,
            'namaInstansi' => $namaInstansi,
            'groupedData' => $groupedData,
            'totalNominal' => $totalNominal,
        ]);

        $filename = "laporan_penerimaan_{$dariBulan}_to_{$sampaiBulan}_{$tahun}.pdf";

        return $pdf->download($filename);
    }
}
