<?php

namespace App\Filament\Resources\JenisPenerimaanTreeResource\Pages;

use App\Filament\Resources\JenisPenerimaanTreeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJenisPenerimaanTrees extends ListRecords
{
    protected static string $resource = JenisPenerimaanTreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
