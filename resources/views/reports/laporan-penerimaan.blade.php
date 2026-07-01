<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penerimaan</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #555;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }
        .metadata-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .metadata-table td {
            padding: 3px 0;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 10px;
            color: #1a1a1a;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }
        .data-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        .data-table td.number {
            text-align: right;
        }
        .data-table tr.total-row {
            font-weight: bold;
            background-color: #eaeaea;
        }
        .footer {
            margin-top: 40px;
            font-size: 10px;
            color: #777;
            text-align: right;
        }
        .signature-section {
            margin-top: 50px;
            width: 100%;
        }
        .signature-box {
            float: right;
            width: 200px;
            text-align: center;
        }
        .signature-space {
            height: 60px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Penerimaan (Posted)</h1>
        <h2>{{ $tenant->nama_bank }}</h2>
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
            <td><strong>Bank/Tenant:</strong></td>
            <td>{{ $tenant->nama_bank }} ({{ $tenant->kode_bank }})</td>
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
                    <th style="width: 20%;">Tanggal</th>
                    <th>Instansi</th>
                    <th style="width: 30%; text-align: right;">Nominal Rincian</th>
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
                        <td>{{ $item->nama_instansi ?? '-' }}</td>
                        <td class="number">Rp {{ number_format($item->nominal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">Total {{ $jenisNama }}</td>
                    <td class="number">Rp {{ number_format($subtotal, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <table class="data-table">
            <tbody>
                <tr>
                    <td colspan="4" style="text-align: center; color: #888;">Tidak ada data rincian penerimaan berstatus Posted untuk periode dan instansi yang dipilih.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    @if($groupedData->isNotEmpty())
        <table class="data-table" style="margin-top: 15px;">
            <tbody>
                <tr class="total-row" style="background-color: #dcdcdc;">
                    <td style="text-align: right; font-size: 13px;">GRAND TOTAL KESELURUHAN</td>
                    <td class="number" style="width: 30%; font-size: 13px;">Rp {{ number_format($totalNominal, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="signature-section">
        <div class="signature-box">
            <p>Disetujui Oleh,</p>
            <div class="signature-space"></div>
            <p><strong>Supervisor Rekon</strong></p>
        </div>
    </div>

</body>
</html>
