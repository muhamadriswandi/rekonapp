<?php

namespace App\Filament\Resources\PindahBukuResource\Pages;

use App\Filament\Resources\PindahBukuResource;
use App\Models\PindahBuku;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPindahBuku extends EditRecord
{
    protected static string $resource = PindahBukuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tutupBuku')
                ->label('Tutup Buku & Posting')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tutup Buku & Posting Pindah Buku?')
                ->modalDescription('Apakah Anda yakin ingin melakukan Tutup Buku & Posting? Aksi ini akan menghitung total debit/kredit transaksi berstatus Validated pada rentang tanggal terpilih, mengunci Pindah Buku, dan memperbarui transaksi menjadi Posted.')
                ->modalSubmitActionLabel('Ya, Tutup Buku')
                ->visible(fn (PindahBuku $record): bool => $record->status === 'Open' && \Illuminate\Support\Facades\Auth::user()?->can('tutupBuku', $record))
                ->action(function (PindahBuku $record) {
                    $record->tutupBukuDanPosting();

                    \Filament\Notifications\Notification::make()
                        ->title('Sukses')
                        ->body("Pindah Buku berhasil ditutup dan diposting.")
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

    protected function afterSave(): void
    {
        $record = $this->record;
        $selectedTransaksi = $this->data['selected_transaksi'] ?? [];

        // Set pindah_buku_id = null for transactions no longer selected
        \App\Models\Transaksi::where('pindah_buku_id', $record->id)
            ->whereNotIn('id', $selectedTransaksi)
            ->update(['pindah_buku_id' => null]);

        // Set pindah_buku_id = record->id for newly selected transactions
        if (!empty($selectedTransaksi)) {
            \App\Models\Transaksi::whereIn('id', $selectedTransaksi)
                ->update(['pindah_buku_id' => $record->id]);
        }
    }
}
