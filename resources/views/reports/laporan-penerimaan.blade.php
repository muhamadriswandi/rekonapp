<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penerimaan</title>
    <style>
        @page {
            margin: 12mm 15mm 12mm 15mm;
            size: A4 landscape;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2C5F8A;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 17px;
            text-transform: uppercase;
            color: #1A3A5C;
            letter-spacing: 0.5px;
        }
        .header h2 {
            margin: 0 0 4px 0;
            font-size: 13px;
            color: #555;
            font-weight: 600;
        }
        .metadata-table {
            width: 100%;
            margin-bottom: 16px;
            font-size: 10.5px;
            background-color: #fcfcfc;
            border: 1px solid #e5e5e5;
            padding: 8px 12px;
            border-radius: 4px;
        }
        .metadata-table td {
            padding: 3px 6px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 8px;
            color: #1A3A5C;
            border-bottom: 1.5px solid #2C5F8A;
            padding-bottom: 3px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th, .data-table td {
            border: 1px solid #dcdcdc;
            padding: 6px 8px;
            text-align: left;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #f3f6f9;
            color: #1A3A5C;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .data-table td.number {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10.5px;
        }
        .data-table tr.total-row {
            font-weight: bold;
            background-color: #eaf2f8;
        }
        .data-table tr.grand-total-row {
            background-color: #1A3A5C;
            color: #ffffff;
            font-weight: bold;
        }
        .data-table tr.grand-total-row td {
            border: 1px solid #1A3A5C;
        }
        .footer {
            margin-top: 30px;
            font-size: 9.5px;
            color: #777;
            text-align: right;
        }
        .signature-section {
            margin-top: 35px;
            width: 100%;
        }
        .signature-box {
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
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Penerimaan (Posted)</h1>
        <h2>{{ $namaBank }}</h2>
    </div>

    <table class="metadata-table">
        <tr>
            <td style="width: 15%;"><strong>Periode Bulan:</strong></td>
            <td style="width: 35%;">
                @if($dariBulan === $sampaiBulan)
                    {{ $dariBulan }} {{ $tahun }}
                @else
                    {{ $dariBulan }} s/d {{ $sampaiBulan }} {{ $tahun }}
                @endif
            </td>
            <td style="width: 15%;"><strong>Instansi:</strong></td>
            <td style="width: 35%;">{{ $namaInstansi }}</td>
        </tr>
        <tr>
            <td><strong>Bank / Cakupan:</strong></td>
            <td>{{ $namaBank }}</td>
            <td><strong>Waktu Cetak:</strong></td>
            <td>{{ now()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }} WIB</td>
        </tr>
    </table>

    @php
        $globalIndex = 1;
    @endphp
    @forelse($groupedData as $jenisNama => $items)
        <div class="section-title">{{ $jenisNama }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">No</th>
                    <th style="width: 11%;">Tanggal</th>
                    <th style="width: 28%;">Deskripsi</th>
                    <th style="width: 18%;">Instansi</th>
                    <th style="width: 18%; text-align: right;">Nominal Rincian</th>
                    <th style="width: 20%;">Bank</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                @endphp
                @foreach($items as $item)
                    @php
                        $subtotal += $item->nominal;
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $globalIndex++ }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->tanggal_transaksi)->translatedFormat('d-m-Y') }}</td>
                        <td>{{ $item->deskripsi ?? '-' }}</td>
                        <td>{{ $item->nama_instansi ?? '-' }}</td>
                        <td class="number">Rp {{ number_format($item->nominal, 2, ',', '.') }}</td>
                        <td>{{ $item->nama_bank }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" style="text-align: right; font-weight: bold;">Subtotal {{ $jenisNama }}</td>
                    <td class="number" style="font-weight: bold;">Rp {{ number_format($subtotal, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @empty
        <table class="data-table">
            <tbody>
                <tr>
                    <td colspan="6" style="text-align: center; color: #888; padding: 20px;">Tidak ada data rincian penerimaan berstatus Posted untuk periode, bank, dan instansi yang dipilih.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    @if($groupedData->isNotEmpty())
        <table class="data-table" style="margin-top: 15px;">
            <tbody>
                <tr class="grand-total-row">
                    <td colspan="4" style="text-align: right; font-size: 11.5px;">GRAND TOTAL KESELURUHAN</td>
                    <td class="number" style="font-size: 11.5px; width: 18%;">Rp {{ number_format($totalNominal, 2, ',', '.') }}</td>
                    <td style="width: 20%;"></td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="signature-section">
        <div class="signature-box">
            <p style="margin-bottom: 5px;">Disetujui Oleh,</p>
            <div class="signature-space"></div>
            <p class="signature-name">Supervisor Rekon</p>
        </div>
    </div>

</body>
</html>
