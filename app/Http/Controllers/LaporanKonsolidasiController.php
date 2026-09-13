<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RelasiBank;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanKonsolidasiController extends Controller
{
    /**
     * Resolves which bank IDs the currently authenticated user is allowed to see.
     * Superadmin sees all. Other roles see only banks linked to their instansi.
     */
    private function getAllowedBankIds(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isSuperadmin()) {
            return RelasiBank::pluck('id')->toArray();
        }

        return $user->getTenants(\Filament\Facades\Filament::getPanel('admin'))
            ->pluck('id')
            ->toArray();
    }

    /**
     * Build the aggregated report data structure, scoped to allowed banks.
     * Returns ['rows' => [...], 'grand_total' => float]
     */
    public function buildReportData(Request $request, array $allowedBankIds): array
    {
        $dariBulan   = $request->query('dari_bulan');
        $sampaiBulan = $request->query('sampai_bulan');
        $tahun       = $request->query('tahun');

        // Raw aggregation: jenis_penerimaan x relasi_bank with Posted transactions
        // scoped to banks the current user is allowed to access
        $data = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 'tr.transaksi_id', '=', 't.id')
            ->leftJoin('periode_pembukuan as pp', 't.periode_pembukuan_id', '=', 'pp.id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->join('relasi_bank as rb', 't.relasi_bank_id', '=', 'rb.id')
            ->select([
                'jp.id as jenis_penerimaan_id',
                'jp.kode',
                'jp.nama as nama_penerimaan',
                'rb.id as relasi_bank_id',
                'rb.nama_bank',
                DB::raw('SUM(tr.nominal) as total_nominal'),
            ])
            ->where('t.status', 'Posted')
            ->where(function ($query) use ($tahun, $dariBulan, $sampaiBulan) {
                $query->where(function ($q) use ($tahun, $dariBulan, $sampaiBulan) {
                    $q->whereNotNull('t.periode_pembukuan_id')
                        ->where('pp.tahun', $tahun)
                        ->whereBetween('pp.bulan', [$dariBulan, $sampaiBulan]);
                })->orWhere(function ($q) use ($tahun, $dariBulan, $sampaiBulan) {
                    $q->whereNull('t.periode_pembukuan_id')
                        ->whereYear('t.tanggal_transaksi', $tahun)
                        ->whereMonth('t.tanggal_transaksi', '>=', $dariBulan)
                        ->whereMonth('t.tanggal_transaksi', '<=', $sampaiBulan);
                });
            })
            ->whereIn('t.relasi_bank_id', $allowedBankIds)  // ← scoped to allowed banks
            ->groupBy('jp.id', 'jp.kode', 'jp.nama', 'rb.id', 'rb.nama_bank')
            ->orderBy('jp.kode')
            ->orderBy('rb.nama_bank')
            ->get();

        // Fetch all JenisPenerimaan to map ancestor hierarchy (Tingkat 1 s/d 5+)
        $allJenis = \App\Models\JenisPenerimaan::all(['id', 'parent_id', 'kode', 'nama'])->keyBy('id');
        $childrenOf = $allJenis->groupBy('parent_id');
        $roots = $allJenis->filter(fn ($j) => ! $j->parent_id || ! $allJenis->has($j->parent_id))->sortBy('kode', SORT_NATURAL);

        // Group transactions by jenis_penerimaan_id
        $txByJenis = $data->groupBy('jenis_penerimaan_id');

        // Recursive roll-up total calculation: node total = direct transactions + sum of children totals
        $getNodeTotal = function ($id) use (&$getNodeTotal, $childrenOf, $txByJenis) {
            $direct = 0;
            $txs = $txByJenis->get($id);
            if ($txs) {
                $direct = (float) $txs->sum('total_nominal');
            }

            $childrenTotal = 0;
            $children = $childrenOf->get($id, collect());
            foreach ($children as $child) {
                $childrenTotal += $getNodeTotal($child->id);
            }

            return $direct + $childrenTotal;
        };

        // Recursive roll-up of bank breakdown for a node and all its descendants
        $getBankTotals = function ($id) use (&$getBankTotals, $childrenOf, $txByJenis) {
            $bankTotals = [];
            $txs = $txByJenis->get($id, collect());
            foreach ($txs as $tx) {
                $bankId = $tx->relasi_bank_id;
                if (!isset($bankTotals[$bankId])) {
                    $bankTotals[$bankId] = [
                        'nama_bank'     => $tx->nama_bank,
                        'total_nominal' => 0.0,
                    ];
                }
                $bankTotals[$bankId]['total_nominal'] += (float) $tx->total_nominal;
            }

            $children = $childrenOf->get($id, collect());
            foreach ($children as $child) {
                $childBanks = $getBankTotals($child->id);
                foreach ($childBanks as $bankId => $b) {
                    if (!isset($bankTotals[$bankId])) {
                        $bankTotals[$bankId] = [
                            'nama_bank'     => $b['nama_bank'],
                            'total_nominal' => 0.0,
                        ];
                    }
                    $bankTotals[$bankId]['total_nominal'] += (float) $b['total_nominal'];
                }
            }

            return $bankTotals;
        };

        $tingkat = (int) ($request->query('tingkat', 5));
        if ($tingkat < 1) {
            $tingkat = 5;
        }

        $rows       = [];
        $grandTotal = 0;

        $traverse = function ($node, $level) use (&$traverse, &$rows, $childrenOf, $getNodeTotal, $getBankTotals, $tingkat) {
            $nodeTotal = $getNodeTotal($node->id);
            if ($nodeTotal <= 0) {
                return;
            }

            $children = $childrenOf->get($node->id, collect())->sortBy('kode', SORT_NATURAL);
            $hasChildren = $children->isNotEmpty();

            if ($hasChildren && $level < $tingkat) {
                // Baris Induk / Rekapitulasi Bertingkat (Roll-up TK 1, TK 2, TK 3, TK 4 dst)
                $rows[] = [
                    'level'           => $level,
                    'kode'            => $node->kode,
                    'nama'            => $node->nama,
                    'nama_penerimaan' => $node->nama,
                    'tenant'          => '-',
                    'jumlah'          => $nodeTotal,
                    'subtotal'        => $nodeTotal,
                    'tenants'         => [],
                ];

                foreach ($children as $child) {
                    $traverse($child, $level + 1);
                }
            } else {
                // Baris Rincian / Terminal (Leaf atau Cutoff Tingkat)
                $bankBreakdown = array_values($getBankTotals($node->id));

                $rows[] = [
                    'level'           => $level,
                    'kode'            => $node->kode,
                    'nama'            => $node->nama,
                    'nama_penerimaan' => $node->nama,
                    'tenant'          => count($bankBreakdown) === 1 ? $bankBreakdown[0]['nama_bank'] : '',
                    'jumlah'          => $nodeTotal,
                    'subtotal'        => $nodeTotal,
                    'tenants'         => $bankBreakdown,
                ];
            }
        };

        foreach ($roots as $root) {
            $rootTotal = $getNodeTotal($root->id);
            if ($rootTotal > 0) {
                $grandTotal += $rootTotal;
                $traverse($root, 1);
            }
        }

        return [
            'rows'        => $rows,
            'grand_total' => $grandTotal,
        ];
    }

    private function getHeaderData(Request $request): array
    {
        $months = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',      6 => 'Juni',      7 => 'Juli',     8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $dariBulan   = (int) $request->query('dari_bulan');
        $sampaiBulan = (int) $request->query('sampai_bulan');
        $tahun       = $request->query('tahun');
        $tingkat     = (int) ($request->query('tingkat', 5));

        $periodeText = $dariBulan === $sampaiBulan
            ? $months[$dariBulan] . ' ' . $tahun
            : $months[$dariBulan] . ' s/d ' . $months[$sampaiBulan] . ' ' . $tahun;

        $tanggalCetak = $request->query('tanggal_cetak')
            ? \Carbon\Carbon::parse($request->query('tanggal_cetak'))->translatedFormat('d F Y')
            : now()->timezone('Asia/Jakarta')->translatedFormat('d F Y');

        return [
            'nama_instansi'   => $request->query('nama_instansi', ''),
            'alamat_instansi' => $request->query('alamat_instansi', ''),
            'judul_laporan'   => $request->query('judul_laporan', 'LAPORAN REKAPITULASI PENERIMAAN DAERAH'),
            'sub_judul'       => $request->query('sub_judul', ''),
            'periode_text'    => $periodeText,
            'tanggal_cetak'   => $tanggalCetak,
            'penandatangan'   => $request->query('penandatangan', ''),
            'dari_bulan_num'  => $dariBulan,
            'sampai_bulan_num'=> $sampaiBulan,
            'tahun'           => $tahun,
            'tingkat'         => $tingkat,
        ];
    }

    public function downloadPdf(Request $request)
    {
        // Auth & role check — hanya user dengan role yang valid
        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasRole(['super_admin', 'Supervisor', 'Operator']), 403);

        $allowedBankIds = $this->getAllowedBankIds();
        abort_if(empty($allowedBankIds), 403, 'Anda tidak memiliki akses ke tenant manapun.');

        $request->validate([
            'dari_bulan'   => 'required|integer|between:1,12',
            'sampai_bulan' => 'required|integer|between:1,12',
            'tahun'        => 'required|integer',
            'tingkat'      => 'nullable|integer|between:1,5',
        ]);

        $header     = $this->getHeaderData($request);
        $reportData = $this->buildReportData($request, $allowedBankIds);

        $pdf = Pdf::loadView('reports.laporan-konsolidasi', array_merge($header, [
            'rows'        => $reportData['rows'],
            'grand_total' => $reportData['grand_total'],
        ]))->setPaper('a4', 'landscape');

        $filename = 'laporan_konsolidasi_' . $header['dari_bulan_num'] . '_' . $header['sampai_bulan_num'] . '_' . $header['tahun'] . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadExcel(Request $request)
    {
        // Auth & role check — hanya user dengan role yang valid
        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasRole(['super_admin', 'Supervisor', 'Operator']), 403);

        $allowedBankIds = $this->getAllowedBankIds();
        abort_if(empty($allowedBankIds), 403, 'Anda tidak memiliki akses ke tenant manapun.');

        $request->validate([
            'dari_bulan'   => 'required|integer|between:1,12',
            'sampai_bulan' => 'required|integer|between:1,12',
            'tahun'        => 'required|integer',
            'tingkat'      => 'nullable|integer|between:1,5',
        ]);

        $header     = $this->getHeaderData($request);
        $reportData = $this->buildReportData($request, $allowedBankIds);
        $rows       = $reportData['rows'];
        $grandTotal = $reportData['grand_total'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Konsolidasi');

        // --- Header Instansi ---
        $currentRow = 1;

        if (!empty($header['nama_instansi'])) {
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", strtoupper($header['nama_instansi']));
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        if (!empty($header['alamat_instansi'])) {
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $header['alamat_instansi']);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        if (!empty($header['nama_instansi']) || !empty($header['alamat_instansi'])) {
            $currentRow++; // blank separator
        }

        // --- Judul ---
        $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", $header['judul_laporan']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;

        if (!empty($header['sub_judul'])) {
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $header['sub_judul']);
            $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        // Periode
        $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'Periode: ' . $header['periode_text']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;

        $currentRow++; // Blank row

        // Meta info (Tanggal Cetak)
        $sheet->setCellValue("A{$currentRow}", 'Tanggal Cetak: ' . $header['tanggal_cetak']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setSize(9)->setItalic(true);
        $currentRow++;

        // --- Header Tabel (4 Kolom) ---
        $headerRowIndex = $currentRow;
        $sheet->setCellValue("A{$currentRow}", 'Kode Penerimaan');
        $sheet->setCellValue("B{$currentRow}", 'Nama Penerimaan');
        $sheet->setCellValue("C{$currentRow}", 'Tenant');
        $sheet->setCellValue("D{$currentRow}", 'Jumlah (Rp)');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C5F8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;

        // --- Data Rows ---
        $dataBorderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
        ];

        $level1Style = [
            'font'    => ['bold' => true, 'color' => ['rgb' => '1A3A5C']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E8F5']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2C5F8A']]],
        ];

        $level2Style = [
            'font'    => ['bold' => true, 'color' => ['rgb' => '2C5F8A']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F5FA']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
        ];

        $subtotalFillStyle = [
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2FB']],
            'font'    => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0C4DE']]],
        ];

        foreach ($rows as $row) {
            $indentSpaces = str_repeat('  ', max(0, $row['level'] - 1));
            $displayName = $indentSpaces . ($row['level'] === 1 ? strtoupper($row['nama_penerimaan']) : $row['nama_penerimaan']);

            if (empty($row['tenants'])) {
                // Baris Induk / Rekapitulasi Bertingkat (Level 1, 2, 3, 4 dst)
                $sheet->setCellValue("A{$currentRow}", $row['kode']);
                $sheet->setCellValue("B{$currentRow}", $displayName);
                $sheet->setCellValue("C{$currentRow}", '-');
                $sheet->setCellValue("D{$currentRow}", $row['jumlah']);
                $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

                if ($row['level'] === 1) {
                    $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($level1Style);
                } elseif ($row['level'] === 2) {
                    $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($level2Style);
                } else {
                    $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray([
                        'font'    => ['bold' => true, 'color' => ['rgb' => '2C5F8A']],
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
                    ]);
                }
                $currentRow++;
            } else {
                // Baris Rincian / Terminal dengan breakdown tenant / bank
                $firstTenantRow = $currentRow;
                foreach ($row['tenants'] as $index => $tenant) {
                    if ($index === 0) {
                        $sheet->setCellValue("A{$currentRow}", $row['kode']);
                        $sheet->setCellValue("B{$currentRow}", $displayName);
                    } else {
                        $sheet->setCellValue("A{$currentRow}", '');
                        $sheet->setCellValue("B{$currentRow}", '');
                    }
                    $sheet->setCellValue("C{$currentRow}", $tenant['nama_bank']);
                    $sheet->setCellValue("D{$currentRow}", $tenant['total_nominal']);
                    $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($dataBorderStyle);
                    $currentRow++;
                }

                if (count($row['tenants']) > 1) {
                    $lastTenantRow = $currentRow - 1;
                    $sheet->mergeCells("A{$firstTenantRow}:A{$lastTenantRow}");
                    $sheet->mergeCells("B{$firstTenantRow}:B{$lastTenantRow}");
                    $sheet->getStyle("A{$firstTenantRow}:B{$lastTenantRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
            }
        }

        // --- Grand Total ---
        $grandTotalStyle = [
            'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0A2040']]],
        ];
        $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'GRAND TOTAL KESELURUHAN');
        $sheet->setCellValue("D{$currentRow}", $grandTotal);
        $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($grandTotalStyle);
        $sheet->getStyle("A{$currentRow}:D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        // --- Penandatangan ---
        if (!empty($header['penandatangan'])) {
            $currentRow += 2;
            $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("C{$currentRow}", 'Tanggal: ' . $header['tanggal_cetak']);

            $currentRow++;
            $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("C{$currentRow}", $header['penandatangan']);
            $sheet->getStyle("C{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Signature space (4 blank rows)
            $signRow = $currentRow + 4;
            $sheet->mergeCells("C{$signRow}:D{$signRow}");
            $sheet->setCellValue("C{$signRow}", '(______________________________)');
            $sheet->getStyle("C{$signRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // --- Column Widths ---
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(22);

        // --- Freeze header ---
        $sheet->freezePane("A" . ($headerRowIndex + 1));

        // --- Write to output ---
        $writer   = new Xlsx($spreadsheet);
        $filename = 'laporan_konsolidasi_' . $header['dari_bulan_num'] . '_' . $header['sampai_bulan_num'] . '_' . $header['tahun'] . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
