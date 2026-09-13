<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use App\Models\TransaksiRincian;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;

class LaporanPenerimaanPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
        public static function getNavigationGroup(): ?string
    {
        return 'Laporan';
    }

    protected static ?string $navigationLabel = 'Laporan Penerimaan';

    protected static ?string $title = 'Laporan Penerimaan';

    protected string $view = 'filament.pages.laporan-penerimaan-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_bulan' => (int) date('m'),
            'sampai_bulan' => (int) date('m'),
            'tahun' => (int) session('active_year', date('Y')),
            'bank_id' => Filament::getTenant()?->id,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Laporan')
                    ->columns(['sm' => 2, 'md' => 3, 'xl' => 5])
                    ->schema([
                        Select::make('dari_bulan')
                            ->label('Dari Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ])
                            ->required()
                            ->live(),
                        Select::make('sampai_bulan')
                            ->label('Sampai Bulan')
                            ->options([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ])
                            ->required()
                            ->live(),
                        Select::make('tahun')
                            ->label('Tahun')
                            ->options(array_combine(range(2020, 2030), range(2020, 2030)))
                            ->required()
                            ->live(),
                        Select::make('bank_id')
                            ->label('Bank')
                            ->options(\App\Models\RelasiBank::pluck('nama_bank', 'id'))
                            ->placeholder('Semua Bank')
                            ->nullable()
                            ->live(),
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->options(\App\Models\Instansi::pluck('nama_instansi', 'id'))
                            ->placeholder('Semua Instansi')
                            ->nullable()
                            ->live(),
                    ])
                    ->headerActions([
                        Action::make('cetak')
                            ->label('Cetak PDF')
                            ->submit('cetakPdf')
                            ->color('primary')
                            ->icon('heroicon-o-printer'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $dariBulan = $this->data['dari_bulan'] ?? date('m');
                $sampaiBulan = $this->data['sampai_bulan'] ?? date('m');
                $tahun = $this->data['tahun'] ?? session('active_year', date('Y'));
                $bankId = $this->data['bank_id'] ?? null;
                $instansiId = $this->data['instansi_id'] ?? null;

                $query = TransaksiRincian::query()
                    ->select('transaksi_rincian.*')
                    ->join('transaksi', 'transaksi_rincian.transaksi_id', '=', 'transaksi.id')
                    ->leftJoin('periode_pembukuan', 'transaksi.periode_pembukuan_id', '=', 'periode_pembukuan.id')
                    ->where('transaksi.status', 'Posted')
                    ->where(function ($q) use ($tahun, $dariBulan, $sampaiBulan) {
                        $q->where(function ($sub) use ($tahun, $dariBulan, $sampaiBulan) {
                            $sub->whereNotNull('transaksi.periode_pembukuan_id')
                                ->where('periode_pembukuan.tahun', $tahun)
                                ->whereBetween('periode_pembukuan.bulan', [$dariBulan, $sampaiBulan]);
                        })->orWhere(function ($sub) use ($tahun, $dariBulan, $sampaiBulan) {
                            $sub->whereNull('transaksi.periode_pembukuan_id')
                                ->whereYear('transaksi.tanggal_transaksi', $tahun)
                                ->whereMonth('transaksi.tanggal_transaksi', '>=', $dariBulan)
                                ->whereMonth('transaksi.tanggal_transaksi', '<=', $sampaiBulan);
                        });
                    });

                if ($bankId) {
                    $query->where('transaksi.relasi_bank_id', $bankId);
                }
                
                if ($instansiId) {
                    $query->where('transaksi.instansi_id', $instansiId);
                }

                return $query;
            })
            ->defaultSort('transaksi.tanggal_transaksi', 'asc')
            ->defaultGroup('jenisPenerimaan.nama')
            ->groupingSettingsHidden()
            ->groups([
                \Filament\Tables\Grouping\Group::make('jenisPenerimaan.nama')
                    ->label('Jenis Penerimaan')
                    ->titlePrefixedWithLabel(false)
            ])
            ->columns([
                TextColumn::make('index')
                    ->rowIndex()
                    ->label('No'),
                TextColumn::make('transaksi.tanggal_transaksi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Tanggal'),
                TextColumn::make('transaksi.deskripsi')
                    ->label('Deskripsi')
                    ->placeholder('-')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('transaksi.instansi.nama_instansi')
                    ->label('Instansi')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('nominal')
                    ->money('idr')
                    ->sortable()
                    ->label('Nominal Rincian')
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total')
                    ),
                TextColumn::make('transaksi.relasiBank.nama_bank')
                    ->label('Bank')
                    ->badge()
                    ->color('info')
                    ->sortable(),
            ])
            ->emptyStateHeading('Tidak Ada Laporan Penerimaan')
            ->emptyStateDescription('Tidak ada rincian transaksi berstatus Posted pada bank, bulan, dan instansi terpilih.');
    }

    public function cetakPdf(): void
    {
        $state = $this->form->getState();

        $dariBulan = $state['dari_bulan'];
        $sampaiBulan = $state['sampai_bulan'];
        $tahun = $state['tahun'];
        $bankId = $state['bank_id'] ?? null;
        $instansiId = $state['instansi_id'] ?? null;

        $params = [
            'tenant' => Filament::getTenant()->id,
            'dari_bulan' => $dariBulan,
            'sampai_bulan' => $sampaiBulan,
            'tahun' => $tahun,
            'bank_id' => $bankId ?: 'all',
        ];

        if ($instansiId) {
            $params['instansi_id'] = $instansiId;
        }

        $url = route('filament.admin.reports.laporan-penerimaan', $params);

        $this->redirect($url);
    }
}
