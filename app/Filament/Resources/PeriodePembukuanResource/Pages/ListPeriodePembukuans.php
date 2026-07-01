<?php

namespace App\Filament\Resources\PeriodePembukuanResource\Pages;

use App\Filament\Resources\PeriodePembukuanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPeriodePembukuans extends ListRecords
{
    protected static string $resource = PeriodePembukuanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
