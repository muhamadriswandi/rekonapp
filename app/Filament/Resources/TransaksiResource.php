<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Models\Transaksi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\BulkAction;
use CodeWithDennis\FilamentSelectTree\SelectTree;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'List Transaksi';

    protected static ?string $modelLabel = 'Transaksi';

    protected static ?string $pluralModelLabel = 'Transaksi';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('tanggal_transaksi')
                    ->label('Tanggal Transaksi'),
                Forms\Components\Textarea::make('deskripsi')
                    ->label('Deskripsi'),
                Forms\Components\TextInput::make('nominal')
                    ->default(0)
                    ->prefix('Rp')
                    ->inputMode('decimal')
                    ->mask(RawJs::make(<<<'JS'
                        $money($input, ',', '.', 2)
                    JS))
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? number_format((float) $state, 2, ',', '.')
                        : null
                    )
                    ->dehydrateStateUsing(fn ($state) => blank($state)
                        ? null
                        : str_replace(',', '.', str_replace('.', '', $state))),
                Forms\Components\Select::make('tipe_mutasi')
                    ->options([
                        'D' => 'Debit (D)',
                        'K' => 'Kredit (K)',
                    ])
                    ->label('Tipe Mutasi'),
                Forms\Components\Select::make('status')
                    ->options([
                        'Raw' => 'Raw',
                        'Verified' => 'Verified',
                        'Validated' => 'Validated',
                        'Posted' => 'Posted',
                    ])
                    ->default('Raw')
                    ->required(),
                Forms\Components\Select::make('kanal_pembayaran_id')
                    ->relationship('kanalPembayaran', 'nama')
                    ->placeholder('Pilih Kanal Pembayaran')
                    ->label('Kanal Pembayaran'),
                Forms\Components\Select::make('instansi_id')
                    ->relationship('instansi', 'nama_instansi')
                    ->placeholder('Pilih Instansi')
                    ->label('Instansi'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_transaksi')
                    ->date()
                    ->sortable()
                    ->label('Tanggal'),
                Tables\Columns\TextColumn::make('deskripsi')
                    ->limit(50)
                    ->searchable()
                    ->label('Deskripsi'),
                Tables\Columns\TextColumn::make('nominal')
                    ->money('idr')
                    ->sortable()
                    ->label('Nominal'),
                Tables\Columns\TextColumn::make('tipe_mutasi')
                    ->badge()
                    ->color(fn ($state) => $state === 'D' ? 'danger' : 'success')
                    ->label('Mutasi'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Raw' => 'gray',
                        'Verified' => 'warning',
                        'Validated' => 'success',
                        'Posted' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('kanalPembayaran.nama')
                    ->label('Kanal')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('instansi.nama_instansi')
                    ->label('Instansi')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Raw' => 'Raw',
                        'Verified' => 'Verified',
                        'Validated' => 'Validated',
                        'Posted' => 'Posted',
                    ])
                    ->placeholder('Pilih Status')
                    ->label('Status'),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                 \Filament\Actions\Action::make('rincian')
                    ->label('Rincian')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->visible(fn (Transaksi $record) => \Illuminate\Support\Facades\Auth::user()?->can('rincian', $record))
                    ->modalHeading('Rincian Pembagian Transaksi')
                    ->modalSubmitActionLabel('Simpan Rincian')
                    ->fillForm(fn (Transaksi $record): array => [
                        'rincian' => $record->rincian->map(fn ($item) => [
                            'jenis_penerimaan_id' => $item->jenis_penerimaan_id,
                            'nominal' => (float)$item->nominal,
                        ])->toArray(),
                    ])
                    ->form([
                        Forms\Components\Placeholder::make('nominal_transaksi')
                            ->label('Total Nominal Transaksi')
                            ->content(fn (Transaksi $record): string => 'Rp ' . number_format($record->nominal, 0, ',', '.')),
                        Forms\Components\Repeater::make('rincian')
                            ->label('Rincian Penerimaan')
                            ->schema([
                                SelectTree::make('jenis_penerimaan_id')
                                    ->query(
                                        query: fn () => \App\Models\JenisPenerimaan::query(),
                                        titleAttribute: 'nama',
                                        parentAttribute: 'parent_id',
                                    )
                                    ->required()
                                    ->placeholder('Pilih Jenis Penerimaan')
                                    ->label('Jenis Penerimaan'),
                                Forms\Components\TextInput::make('nominal')
                                    ->required()
                                     ->label('Nominal')
                                    ->prefix('Rp')
                                    ->inputMode('decimal')
                                    ->mask(RawJs::make(<<<'JS'
                                        $money($input, ',', '.', 2)
                                    JS))
                                    ->formatStateUsing(fn ($state) => filled($state)
                                        ? number_format((float) $state, 2, ',', '.')
                                        : null
                                    )
                                    ->dehydrateStateUsing(fn ($state) => blank($state)
                                        ? null
                                        : str_replace(',', '.', str_replace('.', '', $state)))
                            ])
                            ->minItems(1)
                            ->rules([
                                fn ($get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                     $total = collect($value)->sum(function ($item) {
                                         $nominal = $item['nominal'] ?? 0;
                                         if (is_string($nominal)) {
                                             $nominal = str_replace(',', '.', str_replace('.', '', $nominal));
                                         }
                                         return (float) $nominal;
                                     });
                                     if (abs($total - (float) $record->nominal) > 0.001) {
                                         $fail("Total rincian (Rp " . number_format($total, 2, ',', '.') . ") harus sama dengan nominal transaksi (Rp " . number_format($record->nominal, 2, ',', '.') . ").");
                                     }
                                 }
                            ])
                    ])
                    ->action(function (Transaksi $record, array $data) {
                        $record->rincian()->delete();
                        foreach ($data['rincian'] as $item) {
                            $record->rincian()->create([
                                'jenis_penerimaan_id' => $item['jenis_penerimaan_id'],
                                'nominal' => $item['nominal'],
                            ]);
                        }

                        if ($record->status === 'Raw') {
                            $record->update(['status' => 'Verified']);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Rincian Berhasil Disimpan')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('setInstansi')
                        ->label('Set Instansi')
                        ->icon('heroicon-o-building-office')
                        ->visible(fn () => \Illuminate\Support\Facades\Auth::user()?->can('validate', Transaksi::class))
                        ->modalHeading('Set Instansi untuk Transaksi Terpilih')
                        ->modalSubmitActionLabel('Simpan')
                        ->form([
                            Forms\Components\Select::make('instansi_id')
                                ->relationship('instansi', 'nama_instansi')
                                ->required()
                                ->searchable()
                                ->placeholder('Pilih Instansi')
                                ->label('Instansi'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'instansi_id' => $data['instansi_id'],
                                    'status' => 'Validated',
                                ]);
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Instansi Diperbarui')
                                ->body('Status transaksi terpilih berhasil diubah menjadi Validated.')
                                ->success()
                                ->send();
                        }),
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
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit' => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
