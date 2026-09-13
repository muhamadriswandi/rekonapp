<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi Harian</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.35;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            margin: 0;
            font-size: 13px;
            color: #444;
            font-weight: normal;
        }
        .metadata-table {
            width: 100%;
            margin-bottom: 15px;
            font-size: 10.5px;
            border-collapse: collapse;
        }
        .metadata-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            font-size: 10px;
            vertical-align: top;
        }
        .data-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
            text-align: center;
        }
        .data-table td.number {
            text-align: right;
            white-space: nowrap;
        }
        .data-table tr.total-row {
            font-weight: bold;
            background-color: #f7f7f7;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
            text-align: center;
        }
        .badge-posted { background-color: #d1fae5; color: #065f46; }
        .badge-validated { background-color: #dbeafe; color: #1e40af; }
        .badge-verified { background-color: #fef3c7; color: #92400e; }
        .badge-raw { background-color: #f3f4f6; color: #374151; }
        .text-muted { color: #888; font-style: italic; }
        .footer {
            margin-top: 30px;
            font-size: 9.5px;
            color: #666;
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
            font-size: 10.5px;
        }
        .signature-space {
            height: 50px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>LAPORAN TRANSAKSI HARIAN</h1>
        <h2>{{ $tenant->nama_bank }} ({{ $tenant->kode_bank }})</h2>
    </div>

    <table class="metadata-table">
        <tr>
            <td style="width: 18%;"><strong>Nama Tenant (Bank):</strong></td>
            <td style="width: 35%;">{{ $tenant->nama_bank }} ({{ $tenant->kode_bank }})</td>
            <td style="width: 15%;"><strong>Instansi:</strong></td>
            <td style="width: 32%;">{{ $namaInstansi }}</td>
        </tr>
        <tr>
            <td><strong>Periode:</strong></td>
            <td>
                @if($tanggalMulai === $tanggalSelesai)
                    {{ \Carbon\Carbon::parse($tanggalMulai)->translatedFormat('d F Y') }}
                @else
                    {{ \Carbon\Carbon::parse($tanggalMulai)->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($tanggalSelesai)->translatedFormat('d F Y') }}
                @endif
            </td>
            <td><strong>Waktu Cetak:</strong></td>
            <td>{{ now()->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i:s') }} WIB</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Tanggal</th>
                <th style="width: 26%;">Deskripsi</th>
                <th style="width: 12%;">Nominal</th>
                <th style="width: 9%;">Status</th>
                <th style="width: 18%;">Jenis Penerimaan</th>
                <th style="width: 13%;">Nominal Rincian</th>
                <th style="width: 12%;">Kanal Pembayaran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transaksiList as $item)
                @php
                    $rincianCount = $item->rincian->count();
                    $rowspan = $rincianCount > 1 ? $rincianCount : 1;
                    $firstRincian = $item->rincian->first();
                @endphp
                <tr>
                    <td rowspan="{{ $rowspan }}" style="text-align: center;">
                        {{ \Carbon\Carbon::parse($item->tanggal_transaksi)->format('d/m/Y') }}
                    </td>
                    <td rowspan="{{ $rowspan }}">{{ $item->deskripsi }}</td>
                    <td rowspan="{{ $rowspan }}" class="number">
                        Rp {{ number_format($item->nominal, 0, ',', '.') }}
                    </td>
                    <td rowspan="{{ $rowspan }}" style="text-align: center;">
                        <span class="badge badge-{{ strtolower($item->status) }}">
                            {{ $item->status }}
                        </span>
                    </td>
                    <td>
                        {{ $firstRincian?->jenisPenerimaan?->nama ?? '-' }}
                    </td>
                    <td class="number">
                        {{ $firstRincian ? 'Rp ' . number_format($firstRincian->nominal, 0, ',', '.') : '-' }}
                    </td>
                    <td rowspan="{{ $rowspan }}">{{ $item->kanalPembayaran?->nama ?? '-' }}</td>
                </tr>
                @if($rincianCount > 1)
                    @foreach($item->rincian->slice(1) as $r)
                        <tr>
                            <td>{{ $r->jenisPenerimaan?->nama ?? '-' }}</td>
                            <td class="number">Rp {{ number_format($r->nominal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #888; padding: 15px;">
                        Tidak ada transaksi pada rentang tanggal yang dipilih.
                    </td>
                </tr>
            @endforelse

            @if($transaksiList->isNotEmpty())
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;">Total Keseluruhan ({{ $transaksiList->count() }} Transaksi)</td>
                    <td class="number">Rp {{ number_format($totalNominal, 0, ',', '.') }}</td>
                    <td colspan="4"></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($pivotRows))
        <div style="page-break-inside: avoid; margin-top: 25px;">
            <div style="font-size: 12px; font-weight: bold; margin-bottom: 8px; border-bottom: 1.5px solid #333; padding-bottom: 4px; text-transform: uppercase;">
                Ringkasan Penerimaan Harian (Pivot Rekonsiliasi)
            </div>

            <!-- Pivot Table: Row = Jenis Penerimaan, Col = Kanal Pembayaran, Total di pojok kanan dan bawah -->
            <table class="data-table" style="margin-bottom: 15px;">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">No</th>
                        <th style="width: 25%; text-align: left;">Jenis Penerimaan</th>
                        @foreach($allKanals as $kanal)
                            <th style="text-align: right;">{{ $kanal }}</th>
                        @endforeach
                        <th style="width: 15%; text-align: right; background-color: #e2e8f0;">Total Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pivotRows as $idx => $row)
                        <tr>
                            <td style="text-align: center;">{{ $idx + 1 }}</td>
                            <td>{{ $row['nama'] }}</td>
                            @foreach($allKanals as $kanal)
                                <td class="number">
                                    {{ isset($row['kanals'][$kanal]) && $row['kanals'][$kanal] > 0 ? 'Rp ' . number_format($row['kanals'][$kanal], 0, ',', '.') : '-' }}
                                </td>
                            @endforeach
                            <td class="number" style="font-weight: bold; background-color: #f8fafc;">
                                Rp {{ number_format($row['total'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right; font-weight: bold;">Total Keseluruhan</td>
                        @foreach($allKanals as $kanal)
                            <td class="number" style="font-weight: bold;">
                                Rp {{ number_format($kanalTotals[$kanal] ?? 0, 0, ',', '.') }}
                            </td>
                        @endforeach
                        <td class="number" style="font-weight: bold; background-color: #e2e8f0;">
                            Rp {{ number_format($grandTotalPivot, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-box">
            <p>Petugas Rekonsiliasi,</p>
            <div class="signature-space"></div>
            <p><strong>( .................................................. )</strong></p>
        </div>
    </div>

</body>
</html>
