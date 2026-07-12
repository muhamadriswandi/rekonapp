<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetJenisPenerimaanTool;
use App\Mcp\Tools\GetLaporanKonsolidasiTool;
use App\Mcp\Tools\GetPeriodePembukuanTool;
use App\Mcp\Tools\GetRelasiBankTool;
use App\Mcp\Tools\GetTransaksiPostedTool;
use App\Mcp\Tools\GetTransaksiSummaryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('RekonApp Server')]
#[Version('1.0.0')]
#[Instructions(
    'Server ini menyediakan akses ke data aplikasi RekonApp — sistem rekonsiliasi keuangan daerah. ' .
    'Data yang tersedia: transaksi (Raw, Verified, Validated, Posted), jenis penerimaan hierarki, ' .
    'relasi bank (tenant), periode pembukuan, dan laporan konsolidasi. ' .
    'Gunakan tool GetLaporanKonsolidasiTool untuk mendapatkan laporan konsolidasi lengkap per bulan/tahun.'
)]
class RekonServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        GetTransaksiSummaryTool::class,
        GetTransaksiPostedTool::class,
        GetJenisPenerimaanTool::class,
        GetRelasiBankTool::class,
        GetPeriodePembukuanTool::class,
        GetLaporanKonsolidasiTool::class,
    ];
}
