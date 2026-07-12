<?php

namespace App\Mcp\Tools;

use App\Models\Transaksi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mengambil daftar transaksi dengan status Posted. Mendukung filter opsional: relasi_bank_id, bulan (1-12), dan tahun (contoh: 2024).')]
class GetTransaksiPostedTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return $schema->object([
            'relasi_bank_id' => $schema->integer()
                ->description('ID relasi bank (opsional). Kosongkan untuk semua bank.')
                ->nullable(),
            'bulan' => $schema->integer()
                ->description('Bulan (1-12, opsional).')
                ->minimum(1)
                ->maximum(12)
                ->nullable(),
            'tahun' => $schema->integer()
                ->description('Tahun (contoh: 2024, opsional).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Jumlah maksimal transaksi yang dikembalikan. Default 50.')
                ->nullable(),
        ])->toArray();
    }

    public function handle(Request $request): Response
    {
        $relasiBankId = $request->get('relasi_bank_id');
        $bulan        = $request->get('bulan');
        $tahun        = $request->get('tahun');
        $limit        = $request->get('limit', 50);

        $query = Transaksi::with(['relasiBank', 'kanalPembayaran', 'rincian.jenisPenerimaan'])
            ->where('status', 'Posted');

        if ($relasiBankId) {
            $query->where('relasi_bank_id', $relasiBankId);
        }

        if ($bulan) {
            $query->whereMonth('tanggal_transaksi', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_transaksi', $tahun);
        }

        $transaksi = $query->orderBy('tanggal_transaksi', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($t) {
                return [
                    'id'               => $t->id,
                    'tanggal'          => $t->tanggal_transaksi,
                    'deskripsi'        => $t->deskripsi,
                    'tipe_mutasi'      => $t->tipe_mutasi,
                    'nominal'          => (float) $t->nominal,
                    'status'           => $t->status,
                    'relasi_bank'      => $t->relasiBank?->nama_bank,
                    'kanal_pembayaran' => $t->kanalPembayaran?->nama,
                    'rincian'          => $t->rincian->map(fn ($r) => [
                        'jenis_penerimaan' => $r->jenisPenerimaan?->nama,
                        'kode'             => $r->jenisPenerimaan?->kode,
                        'nominal'          => (float) $r->nominal,
                    ]),
                ];
            });

        return Response::text(json_encode([
            'total' => $transaksi->count(),
            'data'  => $transaksi,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
