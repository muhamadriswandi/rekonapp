<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $judul_laporan }}</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
            size: A4 landscape;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* ---- Header ---- */
        .report-header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 3px solid #2C5F8A;
        }
        .report-header .instansi-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
        }
        .report-header .instansi-address {
            font-size: 9.5px;
            color: #555;
            margin: 0 0 6px 0;
        }
        .report-header .report-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0 2px 0;
            color: #1A3A5C;
        }
        .report-header .report-subtitle {
            font-size: 10.5px;
            color: #444;
            margin: 0 0 4px 0;
        }
        .report-header .report-periode {
            font-size: 10.5px;
            color: #333;
            font-weight: bold;
        }

        /* ---- Meta info ---- */
        .meta-info {
            width: 100%;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .meta-info td {
            padding: 1px 0;
            vertical-align: top;
        }
        .meta-info .label {
            width: 110px;
            font-weight: bold;
            color: #444;
        }
        .meta-info .colon {
            width: 12px;
        }

        /* ---- Main Table ---- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5px;
        }
        .data-table thead tr th {
            background-color: #2C5F8A;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            padding: 6px 6px;
            border: 1px solid #1A3A5C;
            text-align: left;
            letter-spacing: 0.3px;
        }
        .data-table thead tr th.right {
            text-align: right;
        }
        .data-table thead tr th.center {
            text-align: center;
        }
        .data-table tbody tr td {
            border: 1px solid #c8d5e0;
            padding: 4px 6px;
            vertical-align: top;
        }
        .data-table td.number {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            white-space: nowrap;
        }
        .data-table td.kode {
            font-weight: bold;
            color: #1A3A5C;
            white-space: nowrap;
        }

        /* ---- Level Styling ---- */
        .level-1-row td {
            background-color: #dce8f2 !important;
            font-weight: bold;
            color: #102a45;
            border-top: 1.5px solid #2C5F8A;
            border-bottom: 1.5px solid #2C5F8A;
        }
        .level-2-row td {
            background-color: #eef5fa !important;
            font-weight: 600;
            color: #1a3a5c;
            border-top: 1px solid #b8d1e5;
            border-bottom: 1px solid #b8d1e5;
        }
        .level-parent-row td {
            background-color: #f8fafc !important;
            font-weight: 600;
            color: #1a3a5c;
            border-top: 1px solid #d0dbe5;
            border-bottom: 1px solid #d0dbe5;
        }
        .level-leaf-row td {
            background-color: #ffffff;
        }

        /* ---- Grand Total ---- */
        .grand-total-row td {
            background-color: #1A3A5C;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 7px 6px;
            border: 2px solid #0A2040;
        }
        .grand-total-row td.number {
            font-family: 'Courier New', Courier, monospace;
            text-align: right;
        }

        /* ---- Signature ---- */
        .signature-section {
            margin-top: 25px;
            width: 100%;
        }
        .signature-right {
            float: right;
            width: 220px;
            text-align: center;
        }
        .signature-space {
            height: 50px;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        /* ---- Footer ---- */
        .footer {
            margin-top: 18px;
            font-size: 8.5px;
            color: #888;
            text-align: right;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }

        .clearfix::after { content: ''; display: table; clear: both; }
    </style>
</head>
<body>

    {{-- ====== HEADER INSTANSI ====== --}}
    <div class="report-header">
        @if(!empty($nama_instansi))
            <p class="instansi-name">{{ $nama_instansi }}</p>
        @endif
        @if(!empty($alamat_instansi))
            <p class="instansi-address">{{ $alamat_instansi }}</p>
        @endif
        <p class="report-title">{{ $judul_laporan }}</p>
        @if(!empty($sub_judul))
            <p class="report-subtitle">{{ $sub_judul }}</p>
        @endif
        <p class="report-periode">Periode: {{ $periode_text }}</p>
    </div>

    {{-- ====== META INFO ====== --}}
    <table class="meta-info">
        <tr>
            <td class="label">Tanggal Cetak</td>
            <td class="colon">:</td>
            <td>{{ $tanggal_cetak }}</td>
            @if(!empty($penandatangan))
                <td style="width: 40px;"></td>
                <td class="label">Penandatangan</td>
                <td class="colon">:</td>
                <td>{{ $penandatangan }}</td>
            @endif
        </tr>
    </table>

    {{-- ====== MAIN TABLE ====== --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 14%;">Kode Penerimaan</th>
                <th style="width: 26%;">Nama Penerimaan</th>
                @foreach($kanals as $kanal)
                    <th class="right">{{ $kanal['nama'] }}</th>
                @endforeach
                <th class="right" style="width: 12%;">Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php
                    $padding = max(0, ($row['level'] - 1) * 10);
                    $rowClass = $row['level'] === 1
                        ? 'level-1-row'
                        : ($row['level'] === 2
                            ? 'level-2-row'
                            : ($row['is_parent'] ? 'level-parent-row' : 'level-leaf-row'));
                @endphp

                <tr class="{{ $rowClass }}">
                    <td class="kode">{{ $row['kode'] }}</td>
                    <td class="nama" style="padding-left: {{ $padding }}px;">
                        @if($row['level'] === 1)
                            <strong>{{ strtoupper($row['nama']) }}</strong>
                        @elseif($row['level'] === 2 || $row['is_parent'])
                            <strong>{{ $row['nama'] }}</strong>
                        @else
                            {{ $row['nama'] }}
                        @endif
                    </td>

                    @foreach($kanals as $kanal)
                        @php $val = $row['kanals'][$kanal['key']] ?? 0.0; @endphp
                        <td class="number">
                            @if($val > 0)
                                {{ number_format($val, 0, ',', '.') }}
                            @else
                                <span style="color: #bbb;">-</span>
                            @endif
                        </td>
                    @endforeach

                    <td class="number" style="font-weight: bold;">
                        {{ number_format($row['total'], 0, ',', '.') }}
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="{{ 2 + count($kanals) + 1 }}" style="text-align: center; color: #888; padding: 20px;">
                        Tidak ada data transaksi Posted pada periode yang dipilih.
                    </td>
                </tr>
            @endforelse

            {{-- Grand Total --}}
            @if(count($rows) > 0)
                <tr class="grand-total-row">
                    <td colspan="2" style="text-align: right; padding-right: 10px;">
                        GRAND TOTAL KESELURUHAN
                    </td>
                    @foreach($kanals as $kanal)
                        @php $gVal = $grand_total_per_kanal[$kanal['key']] ?? 0.0; @endphp
                        <td class="number">
                            {{ number_format($gVal, 0, ',', '.') }}
                        </td>
                    @endforeach
                    <td class="number">{{ number_format($grand_total, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ====== SIGNATURE ====== --}}
    @if(!empty($penandatangan))
        <div class="clearfix">
            <div class="signature-right">
                <p>{{ $tanggal_cetak }}</p>
                <p>{{ $penandatangan }}</p>
                <div class="signature-space"></div>
                <p class="signature-name">(_____________________________)</p>
            </div>
        </div>
    @endif

    {{-- ====== FOOTER ====== --}}
    <div class="footer">
        Dicetak melalui Sistem Rekonsiliasi Penerimaan Daerah &mdash; {{ now()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }} WIB
    </div>

</body>
</html>
