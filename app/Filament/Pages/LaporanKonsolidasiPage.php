<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;

class LaporanKonsolidasiPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Laporan Konsolidasi';

    protected static ?string $title = 'Laporan Konsolidasi - Semua Bank';

    public static function getNavigationGroup(): ?string
    {
        return 'Laporan';
    }

    protected string $view = 'filament.pages.laporan-konsolidasi-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_bulan'     => (int) date('m'),
            'sampai_bulan'   => (int) date('m'),
            'tahun'          => (int) session('active_year', date('Y')),
            'judul_laporan'  => 'LAPORAN REKAPITULASI PENERIMAAN DAERAH',
            'tanggal_cetak'  => now()->format('Y-m-d'),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $bulanOptions = [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',      6 => 'Juni',      7 => 'Juli',     8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $schema->components([

            Section::make('Filter Periode')
                ->columns(3)
                ->schema([
                    Select::make('dari_bulan')
                        ->label('Dari Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),
                    Select::make('sampai_bulan')
                        ->label('Sampai Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options(array_combine(range(2020, 2030), range(2020, 2030)))
                        ->required()
                        ->live(),
                ]),

            Section::make('Konfigurasi Header Laporan')
                ->description('Informasi ini hanya digunakan sebagai header laporan dan tidak mengubah data master.')
                ->columns(2)
                ->schema([
                    TextInput::make('nama_instansi')
                        ->label('Nama Instansi')
                        ->placeholder('Contoh: BPKPD Provinsi Kalimantan Tengah')
                        ->maxLength(255),
                    TextInput::make('alamat_instansi')
                        ->label('Alamat Instansi')
                        ->placeholder('Contoh: Jl. RTA Milono No. 1 Palangka Raya')
                        ->maxLength(255),
                    TextInput::make('judul_laporan')
                        ->label('Judul Laporan')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('sub_judul')
                        ->label('Sub Judul (Opsional)')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    DatePicker::make('tanggal_cetak')
                        ->label('Tanggal Cetak')
                        ->required()
                        ->displayFormat('d/m/Y'),
                    TextInput::make('penandatangan')
                        ->label('Penandatangan (Opsional)')
                        ->placeholder('Contoh: Kepala Badan, Nama Lengkap')
                        ->maxLength(255),
                ])
                ->headerActions([
                    Action::make('cetakPdf')
                        ->label('Cetak PDF')
                        ->action('cetakPdf')
                        ->color('primary')
                        ->icon('heroicon-o-printer'),
                    Action::make('downloadExcel')
                        ->label('Download Excel')
                        ->action('downloadExcel')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),

        ]);
    }

    protected function buildQueryParams(): array
    {
        $state = $this->form->getState();

        return array_filter([
            'dari_bulan'     => $state['dari_bulan'],
            'sampai_bulan'   => $state['sampai_bulan'],
            'tahun'          => $state['tahun'],
            'nama_instansi'  => $state['nama_instansi'] ?? null,
            'alamat_instansi'=> $state['alamat_instansi'] ?? null,
            'judul_laporan'  => $state['judul_laporan'],
            'sub_judul'      => $state['sub_judul'] ?? null,
            'tanggal_cetak'  => $state['tanggal_cetak'],
            'penandatangan'  => $state['penandatangan'] ?? null,
        ]);
    }

    public function cetakPdf(): void
    {
        $url = route('filament.admin.reports.laporan-konsolidasi', $this->buildQueryParams());
        $this->redirect($url);
    }

    public function downloadExcel(): void
    {
        $url = route('filament.admin.reports.laporan-konsolidasi-excel', $this->buildQueryParams());
        $this->redirect($url);
    }
}
