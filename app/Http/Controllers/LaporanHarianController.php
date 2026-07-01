<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;
use App\Models\RelasiBank;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanHarianController extends Controller
{
    public function downloadPdf($tenant, Request $request)
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

        // Fetch Instansi details
        $instansi = $instansiId ? \App\Models\Instansi::find($instansiId) : null;
        $namaInstansi = $instansi ? $instansi->nama_instansi : 'Konsolidasi (Semua Instansi)';

        // 1. Grouped by Tanggal & Jenis Penerimaan (Pajak/Retribusi Rincian)
        $jenisPenerimaanQuery = DB::table('transaksi as t')
            ->join('transaksi_rincian as tr', 't.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->select([
                't.tanggal_transaksi',
                'jp.id as jenis_penerimaan_id',
                'jp.nama as nama_penerimaan',
                DB::raw('SUM(tr.nominal) as total_nominal'),
            ])
            ->where('t.relasi_bank_id', $activeTenant->id)
            ->whereIn('t.status', ['Validated', 'Posted'])
            ->whereDate('t.tanggal_transaksi', '>=', $tanggalMulai)
            ->whereDate('t.tanggal_transaksi', '<=', $tanggalSelesai);

        if ($instansiId) {
            $jenisPenerimaanQuery->where('t.instansi_id', $instansiId)
                ->groupBy('t.tanggal_transaksi', 'jp.id', 'jp.nama');
        } else {
            // Consolidation: join instansi and group by it too
            $jenisPenerimaanQuery->leftJoin('instansi as i', 't.instansi_id', '=', 'i.id')
                ->addSelect('i.nama_instansi as nama_instansi')
                ->groupBy('t.tanggal_transaksi', 'jp.id', 'jp.nama', 'i.nama_instansi');
        }

        $jenisPenerimaanData = $jenisPenerimaanQuery
            ->orderBy('t.tanggal_transaksi', 'asc')
            ->orderBy('jp.nama', 'asc')
            ->get();

        // 2. Grouped by Kanal Pembayaran (Payment Channels)
        $kanalPembayaranQuery = DB::table('transaksi as t')
            ->leftJoin('kanal_pembayaran as kp', 't.kanal_pembayaran_id', '=', 'kp.id')
            ->select([
                DB::raw('COALESCE(kp.id, 0) as kanal_pembayaran_id'),
                DB::raw("COALESCE(kp.nama, 'Belum Teridentifikasi') as nama_kanal"),
                DB::raw("COALESCE(kp.kode, '-') as kode_kanal"),
                DB::raw('SUM(t.nominal) as total_nominal'),
                DB::raw('COUNT(t.id) as total_count'),
            ])
            ->where('t.relasi_bank_id', $activeTenant->id)
            ->whereIn('t.status', ['Validated', 'Posted'])
            ->whereDate('t.tanggal_transaksi', '>=', $tanggalMulai)
            ->whereDate('t.tanggal_transaksi', '<=', $tanggalSelesai);

        if ($instansiId) {
            $kanalPembayaranQuery->where('t.instansi_id', $instansiId);
        }

        $kanalPembayaranData = $kanalPembayaranQuery
            ->groupBy('kp.id', 'kp.nama', 'kp.kode')
            ->get();

        // Matrix Queries for Section II: Jenis Penerimaan x Kanal Pembayaran
        $penerimaanListQuery = DB::table('transaksi as t')
            ->join('transaksi_rincian as tr', 't.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->select([
                'jp.id',
                'jp.nama',
            ])
            ->where('t.relasi_bank_id', $activeTenant->id)
            ->whereIn('t.status', ['Validated', 'Posted'])
            ->whereDate('t.tanggal_transaksi', '>=', $tanggalMulai)
            ->whereDate('t.tanggal_transaksi', '<=', $tanggalSelesai);

        if ($instansiId) {
            $penerimaanListQuery->where('t.instansi_id', $instansiId);
        }

        $penerimaanList = $penerimaanListQuery
            ->groupBy('jp.id', 'jp.nama')
            ->orderBy('jp.nama', 'asc')
            ->get();

        $matrixSumsQuery = DB::table('transaksi as t')
            ->join('transaksi_rincian as tr', 't.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->leftJoin('kanal_pembayaran as kp', 't.kanal_pembayaran_id', '=', 'kp.id')
            ->select([
                'jp.id as jenis_penerimaan_id',
                DB::raw('COALESCE(kp.id, 0) as kanal_pembayaran_id'),
                DB::raw('SUM(tr.nominal) as total_nominal'),
            ])
            ->where('t.relasi_bank_id', $activeTenant->id)
            ->whereIn('t.status', ['Validated', 'Posted'])
            ->whereDate('t.tanggal_transaksi', '>=', $tanggalMulai)
            ->whereDate('t.tanggal_transaksi', '<=', $tanggalSelesai);

        if ($instansiId) {
            $matrixSumsQuery->where('t.instansi_id', $instansiId);
        }

        $matrixSums = $matrixSumsQuery
            ->groupBy('jp.id', 'kp.id')
            ->get()
            ->groupBy('jenis_penerimaan_id');

        $isConsolidation = empty($instansiId);

        $pdf = Pdf::loadView('reports.laporan-harian', [
            'tenant' => $activeTenant,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'namaInstansi' => $namaInstansi,
            'isConsolidation' => $isConsolidation,
            'jenisPenerimaanData' => $jenisPenerimaanData,
            'kanalPembayaranData' => $kanalPembayaranData,
            'penerimaanList' => $penerimaanList,
            'matrixSums' => $matrixSums,
        ]);

        $filename = $tanggalMulai === $tanggalSelesai
            ? "laporan_harian_{$tanggalMulai}.pdf"
            : "laporan_harian_{$tanggalMulai}_to_{$tanggalSelesai}.pdf";

        return $pdf->download($filename);
    }
}
