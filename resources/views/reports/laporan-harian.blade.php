<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian Rekonsiliasi</title>
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
        <h1>Laporan Harian Rekonsiliasi</h1>
        <h2>{{ $tenant->nama_bank }}</h2>
    </div>

    <table class="metadata-table">
        <tr>
            <td style="width: 15%;"><strong>Tanggal Laporan:</strong></td>
            <td style="width: 35%;">
                @if($tanggalMulai === $tanggalSelesai)
                    {{ \Carbon\Carbon::parse($tanggalMulai)->translatedFormat('d F Y') }}
                @else
                    {{ \Carbon\Carbon::parse($tanggalMulai)->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($tanggalSelesai)->translatedFormat('d F Y') }}
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

    <!-- Table 1: Grouped by Jenis Penerimaan -->
    <div class="section-title">I. Ringkasan Penerimaan Berdasarkan Jenis Penerimaan</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">Tanggal</th>
                <th>Jenis Penerimaan</th>
                @if($isConsolidation)
                    <th>Instansi</th>
                @endif
                <th style="width: 25%; text-align: right;">Total Nominal</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $grandTotalNominalPenerimaan = 0; 
            @endphp
            @forelse($jenisPenerimaanData as $item)
                @php
                    $grandTotalNominalPenerimaan += $item->total_nominal;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->tanggal_transaksi)->translatedFormat('d-m-Y') }}</td>
                    <td>{{ $item->nama_penerimaan }}</td>
                    @if($isConsolidation)
                        <td>{{ $item->nama_instansi ?? 'Tanpa Instansi' }}</td>
                    @endif
                    <td class="number">Rp {{ number_format($item->total_nominal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isConsolidation ? 4 : 3 }}" style="text-align: center; color: #888;">Tidak ada data penerimaan pada periode tanggal ini.</td>
                </tr>
            @endforelse
            @if($jenisPenerimaanData->isNotEmpty())
                <tr class="total-row">
                    <td colspan="{{ $isConsolidation ? 3 : 2 }}" style="text-align: right;">Total Keseluruhan</td>
                    <td class="number">Rp {{ number_format($grandTotalNominalPenerimaan, 2, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Table 2: Grouped by Kanal Pembayaran -->
    <div class="section-title">II. Ringkasan Penerimaan Berdasarkan Kanal Pembayaran</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="text-align: left;">Jenis Penerimaan</th>
                @foreach($kanalPembayaranData as $kanal)
                    <th style="text-align: right;">{{ $kanal->nama_kanal }}</th>
                @endforeach
                <th style="width: 20%; text-align: right;">Total Nominal</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $grandTotalMatrix = 0; 
                $columnTotals = [];
                foreach($kanalPembayaranData as $kanal) {
                    $columnTotals[$kanal->kanal_pembayaran_id] = 0;
                }
            @endphp
            @forelse($penerimaanList as $index => $pajak)
                @php
                    $pajakTotal = 0;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $pajak->nama }}</td>
                    @foreach($kanalPembayaranData as $kanal)
                        @php
                            $nominal = $matrixSums->get($pajak->id)?->firstWhere('kanal_pembayaran_id', $kanal->kanal_pembayaran_id)?->total_nominal ?? 0;
                            $pajakTotal += $nominal;
                            $columnTotals[$kanal->kanal_pembayaran_id] += $nominal;
                        @endphp
                        <td class="number">Rp {{ number_format($nominal, 2, ',', '.') }}</td>
                    @endforeach
                    @php
                        $grandTotalMatrix += $pajakTotal;
                    @endphp
                    <td class="number" style="font-weight: bold;">Rp {{ number_format($pajakTotal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $kanalPembayaranData->count() + 3 }}" style="text-align: center; color: #888;">Tidak ada data penerimaan berdasarkan kanal pembayaran.</td>
                </tr>
            @endforelse
            @if($penerimaanList->isNotEmpty())
                <tr class="total-row">
                    <td colspan="2" style="text-align: right; font-weight: bold;">Total Keseluruhan</td>
                    @foreach($kanalPembayaranData as $kanal)
                        <td class="number" style="font-weight: bold;">Rp {{ number_format($columnTotals[$kanal->kanal_pembayaran_id], 2, ',', '.') }}</td>
                    @endforeach
                    <td class="number" style="font-weight: bold;">Rp {{ number_format($grandTotalMatrix, 2, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <p>Disetujui Oleh,</p>
            <div class="signature-space"></div>
            <p><strong>Supervisor Rekon</strong></p>
        </div>
    </div>

</body>
</html>
