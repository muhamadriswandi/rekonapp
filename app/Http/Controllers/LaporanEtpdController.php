<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\JenisPenerimaan;
use App\Models\KanalPembayaran;
use App\Models\RelasiBank;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanEtpdController extends Controller
{
    /**
     * Get allowed bank IDs for current authenticated user.
     */
    private function getAllowedBankIds(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperadmin()) {
            return RelasiBank::pluck('id')->toArray();
        }

        return $user->getTenants(\Filament\Facades\Filament::getPanel('admin'))
            ->pluck('id')
            ->toArray();
    }

    /**
     * Build the aggregated pivot report data structure for ETPD.
     */
    public function buildReportData(Request $request, array $allowedBankIds): array
    {
        $dariBulan   = (int) $request->query('dari_bulan');
        $sampaiBulan = (int) $request->query('sampai_bulan');
        $tahun       = (int) $request->query('tahun');

        // Raw aggregation: jenis_penerimaan x kanal_pembayaran with Posted transactions
        $rawTxs = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 'tr.transaksi_id', '=', 't.id')
            ->leftJoin('periode_pembukuan as pp', 't.periode_pembukuan_id', '=', 'pp.id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->leftJoin('kanal_pembayaran as kp', 't.kanal_pembayaran_id', '=', 'kp.id')
            ->select([
                'jp.id as jenis_penerimaan_id',
                'kp.id as kanal_id',
                'kp.nama as kanal_nama',
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
            ->whereIn('t.relasi_bank_id', $allowedBankIds)
            ->groupBy('jp.id', 'kp.id', 'kp.nama')
            ->get();

        // 1. Build list of kanals
        $kanals = [];
        $allKanalsFromDb = KanalPembayaran::orderBy('id')->get();
        foreach ($allKanalsFromDb as $k) {
            $kanals[(string) $k->id] = [
                'key'  => (string) $k->id,
                'nama' => $k->nama,
            ];
        }

        // Check if there are transactions without payment channel
        $hasUncategorized = $rawTxs->contains(fn ($row) => is_null($row->kanal_id));
        if ($hasUncategorized || empty($kanals)) {
            $kanals['manual'] = [
                'key'  => 'manual',
                'nama' => 'Lainnya / Manual',
            ];
        }

        // 2. Map direct transactions per jenis_penerimaan and kanal
        $directMap = [];
        foreach ($rawTxs as $t) {
            $jpId = $t->jenis_penerimaan_id;
            $kanalKey = $t->kanal_id ? (string) $t->kanal_id : 'manual';
            $nominal = (float) $t->total_nominal;

            if (!isset($directMap[$jpId])) {
                $directMap[$jpId] = [];
            }
            $directMap[$jpId][$kanalKey] = ($directMap[$jpId][$kanalKey] ?? 0.0) + $nominal;
        }

        // 3. Hierarchy roll-up per kanal
        $allJenis = JenisPenerimaan::all(['id', 'parent_id', 'kode', 'nama'])->keyBy('id');
        $childrenOf = $allJenis->groupBy('parent_id');
        $roots = $allJenis->filter(fn ($j) => ! $j->parent_id || ! $allJenis->has($j->parent_id))->sortBy('kode', SORT_NATURAL);

        $memoKanalTotals = [];
        $getNodeKanalTotals = function ($id) use (&$getNodeKanalTotals, &$memoKanalTotals, $childrenOf, $directMap, $kanals) {
            if (isset($memoKanalTotals[$id])) {
                return $memoKanalTotals[$id];
            }

            $result = [];
            foreach ($kanals as $kKey => $kInfo) {
                $result[$kKey] = 0.0;
            }

            if (isset($directMap[$id])) {
                foreach ($directMap[$id] as $kKey => $amt) {
                    $result[$kKey] = ($result[$kKey] ?? 0.0) + (float) $amt;
                }
            }

            $children = $childrenOf->get($id, collect());
            foreach ($children as $child) {
                $childTotals = $getNodeKanalTotals($child->id);
                foreach ($childTotals as $kKey => $amt) {
                    $result[$kKey] = ($result[$kKey] ?? 0.0) + (float) $amt;
                }
            }

            $memoKanalTotals[$id] = $result;
            return $result;
        };

        $tingkat = (int) ($request->query('tingkat', 5));
        if ($tingkat < 1) {
            $tingkat = 5;
        }

        $rows = [];
        $traverse = function ($node, $level) use (&$traverse, &$rows, $childrenOf, $getNodeKanalTotals, $tingkat) {
            $kanalTotals = $getNodeKanalTotals($node->id);
            $rowTotal = array_sum($kanalTotals);

            if ($rowTotal <= 0) {
                return;
            }

            $children = $childrenOf->get($node->id, collect())->sortBy('kode', SORT_NATURAL);
            $hasChildren = $children->isNotEmpty();

            $rows[] = [
                'level'           => $level,
                'kode'            => $node->kode,
                'nama'            => $node->nama,
                'nama_penerimaan' => $node->nama,
                'kanals'          => $kanalTotals,
                'total'           => $rowTotal,
                'is_parent'       => $hasChildren && $level < $tingkat,
            ];

            if ($hasChildren && $level < $tingkat) {
                foreach ($children as $child) {
                    $traverse($child, $level + 1);
                }
            }
        };

        $grandTotalPerKanal = [];
        foreach ($kanals as $kKey => $kInfo) {
            $grandTotalPerKanal[$kKey] = 0.0;
        }
        $grandTotalOverall = 0.0;

        foreach ($roots as $root) {
            $rootTotals = $getNodeKanalTotals($root->id);
            $rootSum = array_sum($rootTotals);
            if ($rootSum > 0) {
                $grandTotalOverall += $rootSum;
                foreach ($rootTotals as $kKey => $amt) {
                    $grandTotalPerKanal[$kKey] += $amt;
                }
                $traverse($root, 1);
            }
        }

        return [
            'kanals'                => array_values($kanals),
            'rows'                  => $rows,
            'grand_total_per_kanal' => $grandTotalPerKanal,
            'grand_total'           => $grandTotalOverall,
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
        $tahun       = (int) $request->query('tahun');
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
            'judul_laporan'   => $request->query('judul_laporan', 'LAPORAN REALISASI ETPD (KANAL PEMBAYARAN)'),
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

        $pdf = Pdf::loadView('reports.laporan-etpd', array_merge($header, [
            'kanals'                => $reportData['kanals'],
            'rows'                  => $reportData['rows'],
            'grand_total_per_kanal' => $reportData['grand_total_per_kanal'],
            'grand_total'           => $reportData['grand_total'],
        ]))->setPaper('a4', 'landscape');

        $filename = 'laporan_etpd_' . $header['dari_bulan_num'] . '_' . $header['sampai_bulan_num'] . '_' . $header['tahun'] . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadExcel(Request $request)
    {
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
        $kanals     = $reportData['kanals'];
        $rows       = $reportData['rows'];
        $grandTotalPerKanal = $reportData['grand_total_per_kanal'];
        $grandTotal = $reportData['grand_total'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan ETPD');

        // Total columns = 2 (Kode, Nama) + count(kanals) + 1 (Total)
        $totalCols = 2 + count($kanals) + 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        // --- Header Instansi ---
        $currentRow = 1;

        if (!empty($header['nama_instansi'])) {
            $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", strtoupper($header['nama_instansi']));
            $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        if (!empty($header['alamat_instansi'])) {
            $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $header['alamat_instansi']);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        if (!empty($header['nama_instansi']) || !empty($header['alamat_instansi'])) {
            $currentRow++;
        }

        // --- Judul ---
        $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", $header['judul_laporan']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;

        if (!empty($header['sub_judul'])) {
            $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $header['sub_judul']);
            $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        // Periode
        $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'Periode: ' . $header['periode_text']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;

        $currentRow++; // Blank row

        // Meta info (Tanggal Cetak)
        $sheet->setCellValue("A{$currentRow}", 'Tanggal Cetak: ' . $header['tanggal_cetak']);
        $sheet->getStyle("A{$currentRow}")->getFont()->setSize(9)->setItalic(true);
        $currentRow++;

        // --- Header Tabel (Pivot Kanal) ---
        $headerRowIndex = $currentRow;
        $sheet->setCellValue("A{$currentRow}", 'Kode Penerimaan');
        $sheet->setCellValue("B{$currentRow}", 'Nama Penerimaan');

        $colIdx = 3;
        foreach ($kanals as $k) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetter}{$currentRow}", $k['nama']);
            $colIdx++;
        }
        $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue("{$totalColLetter}{$currentRow}", 'Total (Rp)');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C5F8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
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

        foreach ($rows as $row) {
            $indentSpaces = str_repeat('  ', max(0, $row['level'] - 1));
            $displayName = $indentSpaces . ($row['level'] === 1 ? strtoupper($row['nama_penerimaan']) : $row['nama_penerimaan']);

            $sheet->setCellValue("A{$currentRow}", $row['kode']);
            $sheet->setCellValue("B{$currentRow}", $displayName);

            $cIdx = 3;
            foreach ($kanals as $k) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $val = $row['kanals'][$k['key']] ?? 0.0;
                $sheet->setCellValue("{$cLetter}{$currentRow}", $val);
                $sheet->getStyle("{$cLetter}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
                $cIdx++;
            }

            // Total per row
            $tLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue("{$tLetter}{$currentRow}", $row['total']);
            $sheet->getStyle("{$tLetter}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

            if ($row['level'] === 1) {
                $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($level1Style);
            } elseif ($row['level'] === 2) {
                $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($level2Style);
            } else {
                $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($dataBorderStyle);
            }

            $currentRow++;
        }

        // --- Grand Total ---
        $grandTotalStyle = [
            'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0A2040']]],
        ];
        $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'GRAND TOTAL KESELURUHAN');
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $gIdx = 3;
        foreach ($kanals as $k) {
            $gLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gIdx);
            $gVal = $grandTotalPerKanal[$k['key']] ?? 0.0;
            $sheet->setCellValue("{$gLetter}{$currentRow}", $gVal);
            $sheet->getStyle("{$gLetter}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            $gIdx++;
        }
        $gtLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gIdx);
        $sheet->setCellValue("{$gtLetter}{$currentRow}", $grandTotal);
        $sheet->getStyle("{$gtLetter}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($grandTotalStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(22);

        // --- Penandatangan ---
        if (!empty($header['penandatangan'])) {
            $currentRow += 2;
            $signColLetterStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $totalCols - 1));
            $sheet->mergeCells("{$signColLetterStart}{$currentRow}:{$lastColLetter}{$currentRow}");
            $sheet->setCellValue("{$signColLetterStart}{$currentRow}", 'Tanggal: ' . $header['tanggal_cetak']);

            $currentRow++;
            $sheet->mergeCells("{$signColLetterStart}{$currentRow}:{$lastColLetter}{$currentRow}");
            $sheet->setCellValue("{$signColLetterStart}{$currentRow}", $header['penandatangan']);
            $sheet->getStyle("{$signColLetterStart}{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$signColLetterStart}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $signRow = $currentRow + 4;
            $sheet->mergeCells("{$signColLetterStart}{$signRow}:{$lastColLetter}{$signRow}");
            $sheet->setCellValue("{$signColLetterStart}{$signRow}", '(______________________________)');
            $sheet->getStyle("{$signColLetterStart}{$signRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Auto column widths
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(35);
        for ($c = 3; $c <= $totalCols; $c++) {
            $cL = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($cL)->setWidth(18);
        }

        $filename = 'laporan_etpd_' . $header['dari_bulan_num'] . '_' . $header['sampai_bulan_num'] . '_' . $header['tahun'] . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
