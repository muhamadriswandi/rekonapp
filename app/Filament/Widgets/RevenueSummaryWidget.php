<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;

use App\Models\Transaksi;

class RevenueSummaryWidget extends BaseWidget
{
    protected static ?string $heading = 'Ringkasan Pendapatan Daerah';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return $table->query(Transaksi::query()->whereRaw('1=0'));
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthSql = $isSqlite
            ? "CAST(strftime('%m', transaksi.tanggal_transaksi) AS INTEGER) as bulan"
            : 'MONTH(transaksi.tanggal_transaksi) as bulan';
        $yearSql = $isSqlite
            ? "CAST(strftime('%Y', transaksi.tanggal_transaksi) AS INTEGER) as tahun"
            : 'YEAR(transaksi.tanggal_transaksi) as tahun';

        $query = Transaksi::query()
            ->join('transaksi_rincian as tr', 'transaksi.id', '=', 'tr.transaksi_id')
            ->join('jenis_penerimaan as jp', 'tr.jenis_penerimaan_id', '=', 'jp.id')
            ->leftJoin('jenis_penerimaan as parent', 'jp.parent_id', '=', 'parent.id')
            ->join('instansi as i', 'transaksi.instansi_id', '=', 'i.id')
            ->select([
                'i.nama_instansi',
                DB::raw($monthSql),
                DB::raw($yearSql),
                DB::raw('MIN(transaksi.id) as id'),
                DB::raw("SUM(CASE WHEN parent.nama LIKE '%Pajak Daerah%' THEN tr.nominal ELSE 0 END) as total_pajak_daerah"),
                DB::raw("SUM(CASE WHEN parent.nama LIKE '%Retribusi Daerah%' THEN tr.nominal ELSE 0 END) as total_retribusi_daerah"),
                DB::raw("SUM(CASE WHEN parent.nama LIKE '%Pendapatan Lainnya%' OR parent.nama LIKE '%Pendapatan Lain%' THEN tr.nominal ELSE 0 END) as total_pendapatan_lainnya"),
            ])
            ->where('transaksi.relasi_bank_id', $tenant->id)
            ->whereNotNull('transaksi.instansi_id')
            ->whereNotNull('transaksi.tanggal_transaksi')
            ->groupBy('i.nama_instansi', 'bulan', 'tahun');

        return $table
            ->query($query)
            ->defaultSort('i.nama_instansi', 'asc')
            ->defaultKeySort(false)
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('nama_instansi')
                    ->label('Instansi')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('bulan')
                    ->label('Bulan')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                        default => '',
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('tahun')
                    ->label('Tahun')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_pajak_daerah')
                    ->label('Pajak Daerah')
                    ->money('idr')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_retribusi_daerah')
                    ->label('Retribusi Daerah')
                    ->money('idr')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_pendapatan_lainnya')
                    ->label('Pendapatan Lainnya')
                    ->money('idr')
                    ->alignEnd(),
            ]);
    }
}
