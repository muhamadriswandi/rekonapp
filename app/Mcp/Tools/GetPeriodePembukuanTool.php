<?php

namespace App\Mcp\Tools;

use App\Models\PeriodePembukuan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mengembalikan daftar periode pembukuan semua bank beserta statusnya (Open/Closed). Mendukung filter opsional berdasarkan bulan dan tahun.')]
class GetPeriodePembukuanTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return $schema->object([
            'bulan' => $schema->integer()
                ->description('Filter berdasarkan bulan (1-12, opsional).')
                ->minimum(1)
                ->maximum(12)
                ->nullable(),
            'tahun' => $schema->integer()
                ->description('Filter berdasarkan tahun (contoh: 2024, opsional).')
                ->nullable(),
        ])->toArray();
    }

    public function handle(Request $request): Response
    {
        $bulan = $request->get('bulan');
        $tahun = $request->get('tahun');

        $query = PeriodePembukuan::with('relasiBank')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc');

        if ($bulan) {
            $query->where('bulan', $bulan);
        }

        if ($tahun) {
            $query->where('tahun', $tahun);
        }

        $data = $query->get()->map(fn ($p) => [
            'id'           => $p->id,
            'relasi_bank'  => $p->relasiBank?->nama_bank,
            'bulan'        => $p->bulan,
            'tahun'        => $p->tahun,
            'status'       => $p->status,
            'total_debit'  => (float) $p->total_debit,
            'total_kredit' => (float) $p->total_kredit,
            'closed_at'    => $p->closed_at,
        ]);

        $summary = [
            'total_periode' => $data->count(),
            'open'          => $data->where('status', 'Open')->count(),
            'closed'        => $data->where('status', 'Closed')->count(),
        ];

        return Response::text(json_encode([
            'summary' => $summary,
            'data'    => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
