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
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* ---- Header ---- */
        .report-header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 3px solid #2C5F8A;
        }
        .report-header .instansi-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
        }
        .report-header .instansi-address {
            font-size: 10px;
            color: #555;
            margin: 0 0 8px 0;
        }
        .report-header .report-title {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 6px 0 2px 0;
            color: #1A3A5C;
        }
        .report-header .report-subtitle {
            font-size: 11px;
            color: #444;
            margin: 0 0 4px 0;
        }
        .report-header .report-periode {
            font-size: 11px;
            color: #333;
            font-weight: bold;
        }

        /* ---- Meta info ---- */
        .meta-info {
            width: 100%;
            margin-bottom: 12px;
            font-size: 10px;
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
            font-size: 10.5px;
        }
        .data-table thead tr th {
            background-color: #2C5F8A;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 7px 8px;
            border: 1px solid #1A3A5C;
            text-align: left;
            letter-spacing: 0.3px;
        }
        .data-table thead tr th.right {
            text-align: right;
        }
        .data-table tbody tr td {
            border: 1px solid #c8d5e0;
            padding: 5px 8px;
            vertical-align: top;
        }
        .data-table tbody tr.data-row:nth-child(even) {
            background-color: #f5f9fc;
        }
        .data-table td.number {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
        }
        .data-table td.kode {
            font-weight: bold;
            color: #1A3A5C;
            width: 14%;
            white-space: nowrap;
        }
        .data-table td.nama {
            width: 32%;
        }
        .data-table td.tenant {
            width: 25%;
        }
        .data-table td.jumlah {
            width: 20%;
        }

        /* ---- Subtotal row ---- */
        .subtotal-row td {
            background-color: #d6e8f5 !important;
            font-weight: bold;
            border-top: 1.5px solid #2C5F8A;
            border-bottom: 1.5px solid #2C5F8A;
        }

        /* ---- Grand Total ---- */
        .grand-total-row td {
            background-color: #1A3A5C;
            color: #ffffff;
            font-weight: bold;
            font-size: 11.5px;
            padding: 8px;
            border: 2px solid #0A2040;
        }
        .grand-total-row td.number {
            font-family: 'Courier New', Courier, monospace;
            text-align: right;
        }

        /* ---- Signature ---- */
        .signature-section {
            margin-top: 30px;
            width: 100%;
        }
        .signature-right {
            float: right;
            width: 220px;
            text-align: center;
        }
        .signature-space {
            height: 55px;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        /* ---- Footer ---- */
        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #888;
            text-align: right;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }

        .clearfix::after { content: ''; display: table; clear: both; }
    </style>
</head>
<body>

    {{-- ====== HEADER ====== --}}
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
                <th style="width: 13%;">Kode Penerimaan</th>
                <th style="width: 35%;">Nama Penerimaan</th>
                <th style="width: 28%;">Nama Tenant</th>
                <th class="right" style="width: 24%;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp

            @forelse($rows as $row)
                @php $grandTotal += $row['subtotal']; @endphp

                @foreach($row['tenants'] as $tenantIndex => $tenant)
                    <tr class="data-row">
                        {{-- Kode & Nama hanya muncul di baris pertama tiap kode penerimaan --}}
                        <td class="kode">
                            @if($tenantIndex === 0) {{ $row['kode'] }} @endif
                        </td>
                        <td class="nama">
                            @if($tenantIndex === 0) {{ $row['nama_penerimaan'] }} @endif
                        </td>
                        <td class="tenant">{{ $tenant['nama_bank'] }}</td>
                        <td class="number jumlah">{{ number_format($tenant['total_nominal'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach

                {{-- Subtotal per kode penerimaan --}}
                <tr class="subtotal-row">
                    <td colspan="2" style="text-align: right; padding-right: 10px;">
                        Subtotal {{ $row['nama_penerimaan'] }}
                    </td>
                    <td></td>
                    <td class="number">{{ number_format($row['subtotal'], 0, ',', '.') }}</td>
                </tr>

            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #888; padding: 20px;">
                        Tidak ada data transaksi Posted pada periode yang dipilih.
                    </td>
                </tr>
            @endforelse

            {{-- Grand Total --}}
            @if(count($rows) > 0)
                <tr class="grand-total-row">
                    <td colspan="3" style="text-align: right; padding-right: 10px;">
                        GRAND TOTAL KESELURUHAN
                    </td>
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
