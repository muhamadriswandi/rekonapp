<?php

namespace App\Filament\Resources\PeriodePembukuanResource\Pages;

use App\Filament\Resources\PeriodePembukuanResource;
use App\Models\PeriodePembukuan;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPeriodePembukuan extends EditRecord
{
    protected static string $resource = PeriodePembukuanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tutupBuku')
                ->label('Tutup Buku & Posting')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tutup Buku & Posting Periode?')
                ->modalDescription('Apakah Anda yakin ingin melakukan Tutup Buku & Posting? Aksi ini akan menghitung total debit/kredit transaksi berstatus Validated pada periode ini, mengunci periode pembukuan, dan memperbarui transaksi menjadi Posted.')
                ->modalSubmitActionLabel('Ya, Tutup Buku')
                ->visible(fn (PeriodePembukuan $record): bool => $record->status === 'Open' && \Illuminate\Support\Facades\Auth::user()?->can('tutupBuku', $record))
                ->action(function (PeriodePembukuan $record) {
                    $record->tutupBukuDanPosting();

                    \Filament\Notifications\Notification::make()
                        ->title('Sukses')
                        ->body("Periode Pembukuan {$record->bulan}/{$record->tahun} berhasil ditutup.")
                        ->success()
                        ->send();
                    
                    $this->refreshFormData([
                        'status',
                        'total_debit',
                        'total_kredit',
                        'closed_at',
                    ]);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
