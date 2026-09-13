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
            'tingkat' => $schema->integer()
                ->description('Tingkat kedalaman hierarki (1-5). Default: 5.')
                ->minimum(1)
                ->maximum(5),
        ])->required(['bulan', 'tahun'])->toArray();
    }

    public function handle(Request $request): Response
    {
        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');
        $tingkat = (int) ($request->get('tingkat') ?: 5);

        // Ambil aggregate: per jenis_penerimaan + relasi_bank
        $transactions = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 't.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'jp.id', '=', 'tr.jenis_penerimaan_id')
            ->join('relasi_bank as rb', 'rb.id', '=', 't.relasi_bank_id')
            ->where('t.status', 'Posted')
            ->whereMonth('t.tanggal_transaksi', $bulan)
            ->whereYear('t.tanggal_transaksi', $tahun)
            ->select(
                'jp.id as jenis_penerimaan_id',
                'rb.id as relasi_bank_id',
                'rb.nama_bank',
                DB::raw('SUM(tr.nominal) as total_nominal')
            )
            ->groupBy('jp.id', 'rb.id', 'rb.nama_bank')
            ->orderBy('rb.nama_bank')
            ->get();

        $breakdownMap = [];
        $directTotals = [];
        $grandTotalPerBank = [];

        foreach ($transactions as $t) {
            $jpId = $t->jenis_penerimaan_id;
            $nominal = (float) $t->total_nominal;

            if (!isset($breakdownMap[$jpId])) {
                $breakdownMap[$jpId] = [];
            }
            $breakdownMap[$jpId][] = [
                'relasi_bank_id' => $t->relasi_bank_id,
                'nama_bank'      => $t->nama_bank,
                'nominal'        => $nominal,
            ];

            $directTotals[$jpId] = ($directTotals[$jpId] ?? 0) + $nominal;

            if (!isset($grandTotalPerBank[$t->relasi_bank_id])) {
                $grandTotalPerBank[$t->relasi_bank_id] = [
                    'nama_bank' => $t->nama_bank,
                    'total'     => 0,
                ];
            }
            $grandTotalPerBank[$t->relasi_bank_id]['total'] += $nominal;
        }

        $allCategories = JenisPenerimaan::orderBy('kode')->get();
        $categoriesById = $allCategories->keyBy('id');
        $childrenByParent = [];

        foreach ($allCategories as $cat) {
            $parentId = $cat->parent_id ?: 0;
            $childrenByParent[$parentId][] = $cat->id;
        }

        $memoTotals = [];
        $getNodeTotal = function ($id) use (&$getNodeTotal, &$memoTotals, $directTotals, $childrenByParent) {
            if (isset($memoTotals[$id])) {
                return $memoTotals[$id];
            }
            $sum = $directTotals[$id] ?? 0;
            if (isset($childrenByParent[$id])) {
                foreach ($childrenByParent[$id] as $childId) {
                    $sum += $getNodeTotal($childId);
                }
            }
            $memoTotals[$id] = $sum;
            return $sum;
        };

        $getBankTotals = function ($id) use (&$getBankTotals, $childrenByParent, $breakdownMap) {
            $bankTotals = [];
            foreach ($breakdownMap[$id] ?? [] as $b) {
                $bankTotals[$b['relasi_bank_id']] = [
                    'relasi_bank_id' => $b['relasi_bank_id'],
                    'nama_bank'      => $b['nama_bank'],
                    'nominal'        => ($bankTotals[$b['relasi_bank_id']]['nominal'] ?? 0) + $b['nominal'],
                ];
            }
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $childBanks = $getBankTotals($childId);
                foreach ($childBanks as $bankId => $b) {
                    $bankTotals[$bankId] = [
                        'relasi_bank_id' => $bankId,
                        'nama_bank'      => $b['nama_bank'],
                        'nominal'        => ($bankTotals[$bankId]['nominal'] ?? 0) + $b['nominal'],
                    ];
                }
            }
            return $bankTotals;
        };

        $rows = [];
        $flattenTree = function ($parentId, $level) use (&$flattenTree, &$rows, $childrenByParent, $categoriesById, $getBankTotals, $getNodeTotal, $tingkat) {
            if (!isset($childrenByParent[$parentId]) || $level > $tingkat) {
                return;
            }

            foreach ($childrenByParent[$parentId] as $childId) {
                $node = $categoriesById->get($childId);
                if (!$node) continue;

                $subtotal = $getNodeTotal($node->id);
                $hasChildren = isset($childrenByParent[$node->id]) && count($childrenByParent[$node->id]) > 0;
                $isLeaf = !$hasChildren || $level === $tingkat;

                $rows[] = [
                    'id'              => $node->id,
                    'kode'            => $node->kode,
                    'nama_penerimaan' => $node->nama,
                    'level'           => $level,
                    'parent_id'       => $node->parent_id,
                    'tenants'         => $isLeaf ? array_values($getBankTotals($node->id)) : [],
                    'subtotal'        => (float) $subtotal,
                ];

                if ($level < $tingkat) {
                    $flattenTree($node->id, $level + 1);
                }
            }
        };

        $flattenTree(0, 1);

        $totalLevel1 = 0;
        foreach ($rows as $row) {
            if ($row['level'] === 1) {
                $totalLevel1 += $row['subtotal'];
            }
        }

        return Response::text(json_encode([
            'periode'              => ['bulan' => $bulan, 'tahun' => $tahun],
            'tingkat'              => $tingkat,
            'laporan'              => $rows,
            'grand_total'          => (float) $totalLevel1,
            'grand_total_per_bank' => array_values($grandTotalPerBank),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
