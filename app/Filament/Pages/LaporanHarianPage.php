<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use App\Models\Transaksi;
use Filament\Actions\Action;

class LaporanHarianPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        return 'Laporan';
    }

    protected static ?string $navigationLabel = 'Laporan Harian';

    protected static ?string $title = 'Laporan Harian';

    protected string $view = 'filament.pages.laporan-harian-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter')
                    ->description('Pilih Tanggal dan Instansi untuk Laporan Harian')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('tanggal_mulai')
                            ->label('Dari Tanggal')
                            ->required()
                            ->default(now()->format('Y-m-d'))
                            ->live(),
                        DatePicker::make('tanggal_selesai')
                            ->label('Sampai Tanggal')
                            ->required()
                            ->default(now()->format('Y-m-d'))
                            ->afterOrEqual('tanggal_mulai')
                            ->live(),
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->options(\App\Models\Instansi::pluck('nama_instansi', 'id'))
                            ->placeholder('Konsolidasi (Semua Instansi)')
                            ->nullable()
                            ->live(),
                    ])
                    ->headerActions([
                        Action::make('cetak')
                            ->label('Cetak PDF')
                            ->submit('cetakPdf')
                            ->color('primary')
                            ->icon('heroicon-o-arrow-down-tray'),
                    ]),
            ]);   
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $tanggalMulai = $this->data['tanggal_mulai'] ?? now()->format('Y-m-d');
                $tanggalSelesai = $this->data['tanggal_selesai'] ?? now()->format('Y-m-d');
                $instansiId = $this->data['instansi_id'] ?? null;

                $query = Transaksi::query()
                    ->with(['rincian.jenisPenerimaan', 'kanalPembayaran'])
                    ->where('relasi_bank_id', \Filament\Facades\Filament::getTenant()->id)
                    ->whereDate('tanggal_transaksi', '>=', $tanggalMulai)
                    ->whereDate('tanggal_transaksi', '<=', $tanggalSelesai);

                if ($instansiId) {
                    $query->where('instansi_id', $instansiId);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('tanggal_transaksi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Tanggal'),
                TextColumn::make('deskripsi')
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->label('Deskripsi'),
                TextColumn::make('nominal')
                    ->money('idr')
                    ->sortable()
                    ->label('Nominal'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Posted' => 'success',
                        'Validated' => 'info',
                        'Verified' => 'warning',
                        'Raw' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                TextColumn::make('rincian.jenisPenerimaan.nama')
                    ->label('Jenis Penerimaan')
                    ->listWithLineBreaks()
                    ->placeholder('-'),
                TextColumn::make('rincian.nominal')
                    ->label('Nominal Rincian')
                    ->money('idr')
                    ->listWithLineBreaks()
                    ->placeholder('-'),
                TextColumn::make('kanalPembayaran.nama')
                    ->label('Kanal Pembayaran')
                    ->placeholder('-'),
            ])
            ->emptyStateHeading('Tidak Ada Transaksi')
            ->emptyStateDescription('Tidak ada transaksi pada rentang tanggal yang dipilih.');
    }

    public function cetakPdf(): void
    {
        $state = $this->form->getState();

        $tanggalMulai = $state['tanggal_mulai'];
        $tanggalSelesai = $state['tanggal_selesai'];
        $instansiId = $state['instansi_id'] ?? null;

        $url = route('filament.admin.reports.laporan-harian', array_filter([
            'tenant' => \Filament\Facades\Filament::getTenant()->id,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'instansi_id' => $instansiId,
        ]));

        $this->redirect($url);
    }
}
