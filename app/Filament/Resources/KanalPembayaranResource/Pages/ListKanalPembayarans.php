<?php

namespace App\Filament\Resources\KanalPembayaranResource\Pages;

use App\Filament\Resources\KanalPembayaranResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKanalPembayarans extends ListRecords
{
    protected static string $resource = KanalPembayaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
