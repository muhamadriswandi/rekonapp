<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstansiResource\Pages;
use App\Models\Instansi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstansiResource extends Resource
{
    protected static ?string $model = Instansi::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Instansi';

    protected static ?string $modelLabel = 'Instansi';

    protected static ?string $pluralModelLabel = 'Instansi';

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode_instansi')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->label('Kode Instansi'),
                Forms\Components\TextInput::make('nama_instansi')
                    ->required()
                    ->maxLength(255)
                    ->label('Nama Instansi'),
                Forms\Components\CheckboxList::make('relasiBank')
                    ->relationship('relasiBank', 'nama_bank')
                    ->label('Relasi Bank (Tenant) yang Dapat Diakses'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode_instansi')
                    ->label('Kode Instansi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_instansi')
                    ->label('Nama Instansi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('relasiBank.nama_bank')
                    ->badge()
                    ->placeholder('Tidak Ada Akses')
                    ->label('Relasi Bank (Tenant)'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstansis::route('/'),
            'create' => Pages\CreateInstansi::route('/create'),
            'edit' => Pages\EditInstansi::route('/{record}/edit'),
        ];
    }
}
