<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RelasiBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class LaporanKonsolidasiController extends Controller
{
    /**
     * Resolves which bank IDs the currently authenticated user is allowed to see.
     * Superadmin sees all. Other roles see only banks linked to their instansi.
     */
    private function getAllowedBankIds(): array
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

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
    private function buildReportData(Request $request, array $allowedBankIds): array
    {

        $dariBulan   = $request->query('dari_bulan');
        $sampaiBulan = $request->query('sampai_bulan');
        $tahun       = $request->query('tahun');

        // Raw aggregation: jenis_penerimaan x relasi_bank with Posted transactions
        // scoped to banks the current user is allowed to access
        $data = DB::table('transaksi_rincian as tr')
            ->join('transaksi as t', 'tr.transaksi_id', '=', 't.id')
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
            ->whereYear('t.tanggal_transaksi', $tahun)
            ->whereMonth('t.tanggal_transaksi', '>=', $dariBulan)
            ->whereMonth('t.tanggal_transaksi', '<=', $sampaiBulan)
            ->whereIn('t.relasi_bank_id', $allowedBankIds)  // ← scoped to allowed banks
            ->groupBy('jp.id', 'jp.kode', 'jp.nama', 'rb.id', 'rb.nama_bank')
            ->orderBy('jp.kode')
            ->orderBy('rb.nama_bank')
            ->get();

        // Group by jenis_penerimaan
        $rows       = [];
        $grandTotal = 0;

        $byJenis = $data->groupBy('jenis_penerimaan_id');

        foreach ($byJenis as $jenisId => $items) {
            $first    = $items->first();
            $tenants  = [];
            $subtotal = 0;

            foreach ($items as $item) {
                $tenants[] = [
                    'nama_bank'     => $item->nama_bank,
                    'total_nominal' => (float) $item->total_nominal,
                ];
                $subtotal += (float) $item->total_nominal;
            }

            $rows[] = [
                'jenis_penerimaan_id' => $jenisId,
                'kode'                => $first->kode,
                'nama_penerimaan'     => $first->nama_penerimaan,
                'tenants'             => $tenants,
                'subtotal'            => $subtotal,
            ];

            $grandTotal += $subtotal;
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
        ];
    }

    public function downloadPdf(Request $request)
    {
        // Auth & role check — hanya user dengan role yang valid
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()->hasRole(['super_admin', 'Supervisor', 'Operator']), 403);

        $allowedBankIds = $this->getAllowedBankIds();
        abort_if(empty($allowedBankIds), 403, 'Anda tidak memiliki akses ke tenant manapun.');

        $request->validate([
            'dari_bulan'   => 'required|integer|between:1,12',
            'sampai_bulan' => 'required|integer|between:1,12',
            'tahun'        => 'required|integer',
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
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()->hasRole(['super_admin', 'Supervisor', 'Operator']), 403);

        $allowedBankIds = $this->getAllowedBankIds();
        abort_if(empty($allowedBankIds), 403, 'Anda tidak memiliki akses ke tenant manapun.');

        $request->validate([
            'dari_bulan'   => 'required|integer|between:1,12',
            'sampai_bulan' => 'required|integer|between:1,12',
            'tahun'        => 'required|integer',
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
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }

        $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'Periode: ' . $header['periode_text']);
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;

        $currentRow++; // blank line

        // --- Table Header ---
        $headerRowIndex = $currentRow;
        $sheet->setCellValue("A{$currentRow}", 'Kode Penerimaan');
        $sheet->setCellValue("B{$currentRow}", 'Nama Penerimaan');
        $sheet->setCellValue("C{$currentRow}", 'Nama Tenant');
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
        $subtotalFillStyle = [
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2FB']],
            'font'    => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0C4DE']]],
        ];

        foreach ($rows as $row) {
            $firstTenantRow = $currentRow;

            // Tenant rows
            foreach ($row['tenants'] as $index => $tenant) {
                if ($index === 0) {
                    $sheet->setCellValue("A{$currentRow}", $row['kode']);
                    $sheet->setCellValue("B{$currentRow}", $row['nama_penerimaan']);
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

            // Merge kode and nama if multiple tenants
            if (count($row['tenants']) > 1) {
                $lastTenantRow = $currentRow - 1;
                $sheet->mergeCells("A{$firstTenantRow}:A{$lastTenantRow}");
                $sheet->mergeCells("B{$firstTenantRow}:B{$lastTenantRow}");
                $sheet->getStyle("A{$firstTenantRow}:B{$lastTenantRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            }

            // Subtotal row
            $sheet->setCellValue("A{$currentRow}", '');
            $sheet->setCellValue("B{$currentRow}", 'Subtotal ' . $row['nama_penerimaan']);
            $sheet->setCellValue("C{$currentRow}", '');
            $sheet->setCellValue("D{$currentRow}", $row['subtotal']);
            $sheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($subtotalFillStyle);
            $currentRow++;
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
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(40);
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
