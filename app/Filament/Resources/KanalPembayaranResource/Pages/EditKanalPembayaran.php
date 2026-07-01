<?php

namespace App\Filament\Resources\KanalPembayaranResource\Pages;

use App\Filament\Resources\KanalPembayaranResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKanalPembayaran extends EditRecord
{
    protected static string $resource = KanalPembayaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
