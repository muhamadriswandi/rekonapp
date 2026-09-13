<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Models\Transaksi;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    
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
        $canRincian = function (Transaksi $record): bool {
            $user = Auth::user();

            if (! $user instanceof User) {
                return false;
            }

            return $user->can('rincian', $record);
        };

        $canValidate = function (): bool {
            $user = Auth::user();

            if (! $user instanceof User) {
                return false;
            }

            return $user->can('validate', Transaksi::class);
        };

        $rincianAction = \Filament\Actions\Action::make('rincian')
            ->label('Rincian')
            ->icon('heroicon-o-list-bullet')
            ->color('info')
            ->visible($canRincian)
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

                $record->recalculateStatus();

                \Filament\Notifications\Notification::make()
                    ->title('Rincian Berhasil Disimpan')
                    ->success()
                    ->send();
            });

        $viewAction = \Filament\Actions\ViewAction::make();
        $editAction = \Filament\Actions\EditAction::make();
        $deleteAction = \Filament\Actions\DeleteAction::make();

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
                Tables\Columns\TextColumn::make('rincian.jenisPenerimaan.nama')
                    ->label('Jenis Penerimaan')
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
                SelectFilter::make('instansi_id')
                    ->relationship('instansi', 'nama_instansi')
                    ->placeholder('Pilih Instansi')
                    ->label('Instansi'),
                SelectFilter::make('kanal_pembayaran_id')
                    ->relationship('kanalPembayaran', 'nama')
                    ->placeholder('Pilih Kanal')
                    ->label('Kanal'),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                $rincianAction,
                $viewAction,
                $editAction,
                $deleteAction,
            ])
            ->contextMenuActions([
                $rincianAction,
                $viewAction,
                $editAction,
                $deleteAction,
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('setInstansi')
                        ->label('Set Instansi')
                        ->icon('heroicon-o-building-office')
                        ->visible($canValidate)
                        ->modalHeading('Set Instansi untuk Transaksi Terpilih')
                        ->modalSubmitActionLabel('Simpan')
                        ->form([
                            Forms\Components\Select::make('instansi_id')
                                ->relationship('instansi', 'nama_instansi')
                                ->required()
                                ->placeholder('Pilih Instansi')
                                ->label('Instansi'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'instansi_id' => $data['instansi_id'],
                                ]);
                                $record->recalculateStatus();
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Instansi Diperbarui')
                                ->body('Instansi transaksi terpilih berhasil diperbarui.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('setPindahBuku')
                        ->label('Set Pindah Buku')
                        ->icon('heroicon-o-book-open')
                        ->color('primary')
                        ->visible($canValidate)
                        ->modalHeading('Set Pindah Buku untuk Transaksi Terpilih')
                        ->modalSubmitActionLabel('Simpan')
                        ->form([
                            Forms\Components\Select::make('pindah_buku_id')
                                ->relationship(
                                    name: 'pindahBuku', 
                                    modifyQueryUsing: fn ($query) => $query->where('status', 'Open')
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->keterangan ? "{$record->keterangan} ({$record->tanggal_mulai->format('d M Y')} - {$record->tanggal_selesai->format('d M Y')})" : "{$record->tanggal_mulai->format('d M Y')} - {$record->tanggal_selesai->format('d M Y')}")
                                ->required()
                                ->placeholder('Pilih Pindah Buku')
                                ->label('Pindah Buku'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $oldPindahBukuIds = $records->pluck('pindah_buku_id')->filter()->unique();

                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'pindah_buku_id' => $data['pindah_buku_id'],
                                ]);
                                $record->recalculateStatus();
                            });

                            // Recalculate totals for the newly assigned Pindah Buku
                            if (isset($data['pindah_buku_id'])) {
                                \App\Models\PindahBuku::find($data['pindah_buku_id'])?->recalculateTotals();
                            }

                            // Recalculate totals for any previously assigned Pindah Buku
                            foreach ($oldPindahBukuIds as $oldId) {
                                if ($oldId != $data['pindah_buku_id']) {
                                    \App\Models\PindahBuku::find($oldId)?->recalculateTotals();
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Pindah Buku Diperbarui')
                                ->body('Pindah Buku transaksi terpilih berhasil diperbarui.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('setJenisPenerimaan')
                        ->label('Set Jenis Penerimaan')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('#3490dc')
                        ->visible($canValidate)
                        ->modalHeading('Set Jenis Penerimaan untuk Transaksi Terpilih')
                        ->modalSubmitActionLabel('Simpan')
                        ->form([
                            SelectTree::make('jenis_penerimaan_id')
                                ->query(
                                    query: fn () => \App\Models\JenisPenerimaan::query(),
                                    titleAttribute: 'nama',
                                    parentAttribute: 'parent_id',
                                )
                                ->required()
                                ->placeholder('Pilih Jenis Penerimaan')
                                ->label('Jenis Penerimaan'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $rincian = $record->rincian()->firstOrNew();
                                $rincian->jenis_penerimaan_id = $data['jenis_penerimaan_id'];
                                $rincian->nominal = $rincian->exists ? $rincian->nominal : $record->nominal;
                                $rincian->save();
                                
                                $record->recalculateStatus();
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Jenis Penerimaan Diperbarui')
                                ->body('Jenis Penerimaan transaksi terpilih berhasil diperbarui.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('setKanalPembayaran')
                        ->label('Set Kanal Pembayaran')
                        ->icon('heroicon-o-document-currency-dollar')
                        ->visible($canValidate)
                        ->modalHeading('Set Kanal Pembayaran untuk Transaksi Terpilih')
                        ->modalSubmitActionLabel('Simpan')
                        ->form([
                            Forms\Components\Select::make('kanal_pembayaran_id')
                                ->relationship('kanalPembayaran', 'nama')
                                ->required()
                                ->placeholder('Pilih Kanal Pembayaran')
                                ->label('Kanal Pembayaran'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'kanal_pembayaran_id' => $data['kanal_pembayaran_id'],
                                ]);
                                $record->recalculateStatus();
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Kanal Pembayaran Diperbarui')
                                ->body('Kanal Pembayaran transaksi terpilih berhasil diperbarui.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('setPost')
                        ->label('Set Post')
                        ->icon('heroicon-o-arrow-up-on-square-stack')
                        ->color('success')
                        ->visible($canValidate)
                        ->requiresConfirmation()
                        ->modalHeading('Set Post Transaksi Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin mem-posting transaksi terpilih?')
                        ->modalSubmitActionLabel('Ya, Post')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function ($record) {
                                $record->updateQuietly(['status' => 'Posted']);
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Transaksi Di-post')
                                ->body('Transaksi terpilih berhasil di-post.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('setUnPost')
                        ->label('Set UnPost')
                        ->icon('heroicon-o-arrow-down-on-square-stack')
                        ->color('warning')
                        ->visible($canValidate)
                        ->requiresConfirmation()
                        ->modalHeading('Set UnPost Transaksi Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin melakukan un-post pada transaksi terpilih? Status akan dihitung ulang secara otomatis.')
                        ->modalSubmitActionLabel('Ya, UnPost')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'Raw']); // Will trigger recalculateStatus automatically
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Transaksi Di-unpost')
                                ->body('Transaksi terpilih berhasil di-unpost dan statusnya dihitung ulang.')
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
