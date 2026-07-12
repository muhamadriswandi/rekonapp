<?php

namespace App\Mcp\Tools;

use App\Models\JenisPenerimaan;
use App\Models\RelasiBank;
use App\Models\TransaksiRincian;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Menghasilkan data laporan konsolidasi terstruktur: dikelompokkan per kode penerimaan (hierarki) kemudian per tenant/bank. Hanya transaksi Posted yang diambil. Wajib isi bulan dan tahun.')]
class GetLaporanKonsolidasiTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return $schema->object([
            'bulan' => $schema->integer()
                ->description('Bulan laporan (1-12).')
                ->minimum(1)
                ->maximum(12),
            'tahun' => $schema->integer()
                ->description('Tahun laporan (contoh: 2024).'),
        ])->required(['bulan', 'tahun'])->toArray();
    }

    public function handle(Request $request): Response
    {
        $bulan = $request->get('bulan');
        $tahun = $request->get('tahun');

        // Ambil aggregate: per jenis_penerimaan + relasi_bank
        $rows = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 't.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'jp.id', '=', 'tr.jenis_penerimaan_id')
            ->join('relasi_bank as rb', 'rb.id', '=', 't.relasi_bank_id')
            ->where('t.status', 'Posted')
            ->whereMonth('t.tanggal_transaksi', $bulan)
            ->whereYear('t.tanggal_transaksi', $tahun)
            ->select(
                'jp.id as jenis_penerimaan_id',
                'jp.kode',
                'jp.nama as nama_penerimaan',
                'jp.parent_id',
                'rb.id as relasi_bank_id',
                'rb.nama_bank',
                DB::raw('SUM(tr.nominal) as total_nominal')
            )
            ->groupBy('jp.id', 'jp.kode', 'jp.nama', 'jp.parent_id', 'rb.id', 'rb.nama_bank')
            ->orderBy('jp.kode')
            ->orderBy('rb.nama_bank')
            ->get();

        // Kelompokkan per kode penerimaan
        $grouped = $rows->groupBy('jenis_penerimaan_id');

        $result = [];
        $grandTotal = 0;
        $grandTotalPerBank = [];

        foreach ($grouped as $jenisPenerimaanId => $tenants) {
            $first       = $tenants->first();
            $subtotal    = $tenants->sum('total_nominal');
            $grandTotal += $subtotal;

            $tenantList = $tenants->map(function ($row) use (&$grandTotalPerBank) {
                $nominal = (float) $row->total_nominal;

                if (!isset($grandTotalPerBank[$row->relasi_bank_id])) {
                    $grandTotalPerBank[$row->relasi_bank_id] = [
                        'nama_bank' => $row->nama_bank,
                        'total'     => 0,
                    ];
                }
                $grandTotalPerBank[$row->relasi_bank_id]['total'] += $nominal;

                return [
                    'relasi_bank_id' => $row->relasi_bank_id,
                    'nama_bank'      => $row->nama_bank,
                    'nominal'        => $nominal,
                ];
            })->values();

            $result[] = [
                'jenis_penerimaan_id' => $first->jenis_penerimaan_id,
                'kode'                => $first->kode,
                'nama_penerimaan'     => $first->nama_penerimaan,
                'parent_id'           => $first->parent_id,
                'tenants'             => $tenantList,
                'subtotal'            => (float) $subtotal,
            ];
        }

        // Sort by kode hierarki
        usort($result, fn ($a, $b) => strnatcmp($a['kode'] ?? '', $b['kode'] ?? ''));

        return Response::text(json_encode([
            'periode'               => ['bulan' => $bulan, 'tahun' => $tahun],
            'laporan'               => $result,
            'grand_total'           => (float) $grandTotal,
            'grand_total_per_bank'  => array_values($grandTotalPerBank),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
