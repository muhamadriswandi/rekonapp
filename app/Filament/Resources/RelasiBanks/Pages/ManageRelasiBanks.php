<?php

namespace App\Filament\Resources\RelasiBanks\Pages;

use App\Filament\Resources\RelasiBanks\RelasiBankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRelasiBanks extends ManageRecords
{
    protected static string $resource = RelasiBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
