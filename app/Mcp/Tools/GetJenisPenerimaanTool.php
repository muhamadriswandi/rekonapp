<?php

namespace App\Mcp\Tools;

use App\Models\JenisPenerimaan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Mengembalikan daftar lengkap jenis penerimaan beserta hierarki parent-child, kode, dan nama. Berguna untuk memahami struktur kode penerimaan (contoh: 4 → 4.1 → 4.1.1).')]
class GetJenisPenerimaanTool extends Tool
{
    public function handle(Request $request): Response
    {
        $all = JenisPenerimaan::orderBy('kode')->get();

        // Build hierarchical structure
        $roots = $all->whereNull('parent_id')->values();

        $tree = $roots->map(fn ($item) => $this->buildTree($item, $all));

        return Response::text(json_encode([
            'total'      => $all->count(),
            'hierarki'   => $tree,
            'flat_list'  => $all->map(fn ($j) => [
                'id'        => $j->id,
                'kode'      => $j->kode,
                'nama'      => $j->nama,
                'parent_id' => $j->parent_id,
                'level'     => substr_count($j->kode ?? '', '.'),
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function buildTree($item, $all): array
    {
        $children = $all->where('parent_id', $item->id)->values();

        return [
            'id'       => $item->id,
            'kode'     => $item->kode,
            'nama'     => $item->nama,
            'children' => $children->map(fn ($child) => $this->buildTree($child, $all))->values(),
        ];
    }
}
