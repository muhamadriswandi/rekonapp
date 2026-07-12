<?php

namespace App\Mcp\Tools;

use App\Models\Transaksi;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Menampilkan ringkasan jumlah dan nominal transaksi berdasarkan status (Raw, Verified, Validated, Posted).')]
class GetTransaksiSummaryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $statuses = ['Raw', 'Verified', 'Validated', 'Posted'];

        $summary = [];

        foreach ($statuses as $status) {
            $query = Transaksi::where('status', $status);

            $summary[$status] = [
                'jumlah_transaksi' => $query->count(),
                'total_debit'      => (float) Transaksi::where('status', $status)->where('tipe_mutasi', 'D')->sum('nominal'),
                'total_kredit'     => (float) Transaksi::where('status', $status)->where('tipe_mutasi', 'K')->sum('nominal'),
            ];
        }

        $grandTotal = Transaksi::count();

        return Response::text(json_encode([
            'ringkasan_per_status' => $summary,
            'grand_total_transaksi' => $grandTotal,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
