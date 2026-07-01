<?php

namespace App\Filament\Resources\PindahBukuResource\Pages;

use App\Filament\Resources\PindahBukuResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePindahBuku extends CreateRecord
{
    protected static string $resource = PindahBukuResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['relasi_bank_id'] = \Filament\Facades\Filament::getTenant()?->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $selectedTransaksi = $this->data['selected_transaksi'] ?? [];
        if (!empty($selectedTransaksi)) {
            \App\Models\Transaksi::whereIn('id', $selectedTransaksi)
                ->update(['pindah_buku_id' => $record->id]);
        }
    }
}
