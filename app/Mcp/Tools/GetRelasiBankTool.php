<?php

namespace App\Mcp\Tools;

use App\Models\RelasiBank;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mengembalikan daftar semua relasi bank (tenant) yang terdaftar di aplikasi. Setiap bank memiliki id dan nama_bank.')]
class GetRelasiBankTool extends Tool
{
    public function handle(Request $request): Response
    {
        $banks = RelasiBank::orderBy('nama_bank')
            ->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'nama_bank' => $b->nama_bank,
            ]);

        return Response::text(json_encode([
            'total' => $banks->count(),
            'data'  => $banks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
