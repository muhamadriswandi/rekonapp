<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JenisPenerimaanResource\Pages;
use App\Models\JenisPenerimaan;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Html;

class JenisPenerimaanResource extends Resource
{
    protected static ?string $model = JenisPenerimaan::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Jenis Penerimaan';

    protected static ?string $modelLabel = 'Jenis Penerimaan';

    protected static ?string $pluralModelLabel = 'Jenis Penerimaan';

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Input Data Jenis Penerimaan')
                    ->schema([
                        Forms\Components\TextInput::make('kode')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'nama', fn ($query, $record) => $record ? $query->where('id', '!=', $record->id) : $query)
                            ->searchable()
                            ->placeholder('Pilih Kategori Induk (jika ada)')
                            ->label('Kategori Induk'),
                        Forms\Components\TextInput::make('regex_pattern')
                            ->maxLength(255)
                            ->nullable()
                            ->label('Regex Pattern')
                            ->helperText('Masukkan kata kunci pencarian atau ekspresi reguler lengkap (Regex).'),
                    ])
                    ->columnSpan(2),

                Section::make('Panduan Regex Pattern')
                    ->description('Tata cara pencocokan deskripsi otomatis')
                    ->schema([
                        Html::make('
                            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                <p>Pola regex ini digunakan untuk mencocokkan kata kunci dalam deskripsi rekening koran secara otomatis.</p>
                                
                                <div class="border-l-4 border-amber-500 pl-4 py-1">
                                    <strong class="text-gray-800 dark:text-gray-200">1. Pencocokan Teks Sederhana (Case-Insensitive)</strong>
                                    <p class="mt-1 text-xs text-gray-500">Cukup masukkan kata kunci atau substring biasa. Pencocokan tidak sensitif huruf besar/kecil.</p>
                                    <p class="mt-1 font-mono text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded">Contoh: <strong>Pajak Hotel</strong> atau <strong>Retribusi</strong></p>
                                    <p class="text-xs text-gray-500 mt-1">Mencocokkan: "Setoran Pajak Hotel Bengkulu" atau "Pembayaran Retribusi Kebersihan".</p>
                                </div>

                                <div class="border-l-4 border-amber-500 pl-4 py-1">
                                    <strong class="text-gray-800 dark:text-gray-200">2. Ekspresi Reguler (Regex) Lengkap</strong>
                                    <p class="mt-1 text-xs text-gray-500">Gunakan format regex lengkap yang diawali/diakhiri slash <code>/</code> beserta flag pendukung.</p>
                                    <p class="mt-1 font-mono text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded">Contoh: <strong>/pajak.*reklame/i</strong> atau <strong>/(hotel|penginapan)/i</strong></p>
                                    <p class="text-xs text-gray-500 mt-1">Mencocokkan: "Setoran Pajak Daerah Reklame" atau "Pembayaran Hotel Grand".</p>
                                </div>
                            </div>
                        ')
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.nama')
                    ->label('Kategori Induk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('regex_pattern')
                    ->searchable(),
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
            'index' => Pages\ListJenisPenerimaans::route('/'),
            'create' => Pages\CreateJenisPenerimaan::route('/create'),
            'edit' => Pages\EditJenisPenerimaan::route('/{record}/edit'),
        ];
    }
}
