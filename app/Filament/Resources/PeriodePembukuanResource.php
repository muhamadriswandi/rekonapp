<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PeriodePembukuanResource\Pages;
use App\Models\PeriodePembukuan;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PeriodePembukuanResource extends Resource
{
    protected static ?string $model = PeriodePembukuan::class;
    
    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Periode Pembukuan';

    protected static ?string $modelLabel = 'Periode Pembukuan';

    protected static ?string $pluralModelLabel = 'Periode Pembukuan';

    public static function isScopedToTenant(): bool
    {
        return true;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bulan')
                    ->options([
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                    ])
                    ->required()
                    ->label('Bulan'),
                Forms\Components\TextInput::make('tahun')
                    ->numeric()
                    ->required()
                    ->default(date('Y'))
                    ->label('Tahun'),
                Forms\Components\Select::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Closed' => 'Closed',
                    ])
                    ->default('Open')
                    ->required()
                    ->label('Status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bulan')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                        default => '',
                    })
                    ->sortable()
                    ->label('Bulan'),
                Tables\Columns\TextColumn::make('tahun')
                    ->sortable()
                    ->label('Tahun'),
                Tables\Columns\TextColumn::make('total_debit')
                    ->money('idr')
                    ->sortable()
                    ->label('Total Debit'),
                Tables\Columns\TextColumn::make('total_kredit')
                    ->money('idr')
                    ->sortable()
                    ->label('Total Kredit'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Open' => 'success',
                        'Closed' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('closed_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->label('Waktu Tutup'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('tutupBuku')
                    ->label('Tutup Buku & Posting')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Tutup Buku & Posting Periode?')
                    ->modalDescription('Apakah Anda yakin ingin melakukan Tutup Buku & Posting? Aksi ini akan menghitung total debit/kredit transaksi berstatus Validated pada periode ini, mengunci periode pembukuan, dan memperbarui transaksi menjadi Posted.')
                    ->modalSubmitActionLabel('Ya, Tutup Buku')
                    ->visible(fn (PeriodePembukuan $record): bool => $record->status === 'Open' && \Illuminate\Support\Facades\Auth::user()?->can('tutupBuku', $record))
                    ->action(function (PeriodePembukuan $record) {
                        $record->tutupBukuDanPosting();

                        \Filament\Notifications\Notification::make()
                            ->title('Sukses')
                            ->body("Periode Pembukuan {$record->bulan}/{$record->tahun} berhasil ditutup.")
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\ViewAction::make(),
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
            'index' => Pages\ListPeriodePembukuans::route('/'),
            'create' => Pages\CreatePeriodePembukuan::route('/create'),
            'edit' => Pages\EditPeriodePembukuan::route('/{record}/edit'),
        ];
    }
}
