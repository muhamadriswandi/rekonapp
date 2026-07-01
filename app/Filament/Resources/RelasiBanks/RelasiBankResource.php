<?php

namespace App\Filament\Resources\RelasiBanks;

use App\Filament\Resources\RelasiBanks\Pages\ManageRelasiBanks;
use App\Models\RelasiBank;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RelasiBankResource extends Resource
{
    protected static ?string $model = RelasiBank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama_bank';

    protected static ?string $navigationLabel = 'Relasi Bank (Tenant)';

    protected static ?string $modelLabel = 'Relasi Bank';

    protected static ?string $pluralModelLabel = 'Relasi Bank';

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_bank')
                    ->required(),
                TextInput::make('nama_bank')
                    ->required(),
                \Filament\Forms\Components\CheckboxList::make('instansi')
                    ->relationship('instansi', 'nama_instansi')
                    ->label('Instansi / OPD yang Mengakses'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_bank')
            ->columns([
                TextColumn::make('kode_bank')
                    ->searchable(),
                TextColumn::make('nama_bank')
                    ->searchable(),
                TextColumn::make('instansi.nama_instansi')
                    ->badge()
                    ->placeholder('Global / Semua Instansi')
                    ->label('Instansi / OPD'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRelasiBanks::route('/'),
        ];
    }
}
