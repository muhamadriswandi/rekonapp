<?php

namespace App\Filament\Resources\PindahBukuResource\Pages;

use App\Filament\Resources\PindahBukuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPindahBukus extends ListRecords
{
    protected static string $resource = PindahBukuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
