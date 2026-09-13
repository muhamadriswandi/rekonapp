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
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class ListTransaksis extends ListRecords
{
    protected static string $resource = TransaksiResource::class;

public function table(Table $table): Table
{
    return $table
    ->modifyQueryUsing(function (Builder $query) {
    $query
        ->whereYear('tanggal_transaksi', session('active_year', now()->year));
    });
}

    public function getTabs(): array
    {
        $statuses = [
            'Raw' => 'gray',
            'Verified' => 'warning',
            'Validated' => 'success',
            'Posted' => 'info',
        ];

        $tabs = [
            'all' => Tab::make('Semua')
                ->badge(Transaksi::count()),
        ];

        foreach ($statuses as $status => $color) {
            $tabs[strtolower($status)] = Tab::make($status)
                ->badge(fn () => Transaksi::where('status', $status)->count())
                ->badgeColor($color)
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', $status)
                );
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
                ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('uploadCsv', Transaksi::class))
                ->modalHeading('Upload File CSV Transaksi')
                ->modalSubmitActionLabel('Impor')
                ->schema([
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
