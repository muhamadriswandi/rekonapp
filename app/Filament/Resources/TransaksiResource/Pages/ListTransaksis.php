<?php

namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Filament\Schemas\Components\Tabs\Tab;

class ListTransaksis extends ListRecords
{
    protected static string $resource = TransaksiResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return parent::getTableQuery()
            ->whereYear('tanggal_transaksi', session('active_year', now()->year));
    }

    public function getTabs(): array
    {
        $months = [
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
        ];

        $tabs = [
            'semua' => Tab::make('Semua'),
        ];

        foreach ($months as $num => $name) {
            $tabs[strtolower($name)] = Tab::make($name)
                ->query(fn ($query) => $query->whereMonth('tanggal_transaksi', $num));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('uploadCsv')
                ->label('Upload CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => \Illuminate\Support\Facades\Auth::user()?->can('uploadCsv', Transaksi::class))
                ->modalHeading('Upload File CSV Transaksi')
                ->modalSubmitActionLabel('Impor')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Pilih File CSV')
                        ->required()
                        ->disk('local')
                        ->directory('temp-csv')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->preventFilePathTampering(),
                ])
                ->action(function (array $data) {
                    $tenantId = Filament::getTenant()->id;
                    $filePath = $data['csv_file'];

                    // Dispatch job to queue
                    \App\Jobs\ProcessCsvImportJob::dispatch($filePath, $tenantId);

                    Notification::make()
                        ->title('Impor CSV Diproses')
                        ->body('Data transaksi sedang diproses di latar belakang.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
