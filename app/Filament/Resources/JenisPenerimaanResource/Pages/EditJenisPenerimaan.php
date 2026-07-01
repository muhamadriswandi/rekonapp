<?php

namespace App\Filament\Resources\JenisPenerimaanResource\Pages;

use App\Filament\Resources\JenisPenerimaanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJenisPenerimaan extends EditRecord
{
    protected static string $resource = JenisPenerimaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
