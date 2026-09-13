<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PindahBukuResource\Pages;
use App\Models\PindahBuku;
use App\Models\User;
use Filament\Forms;

use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Schemas\Components\Section;

class PindahBukuResource extends Resource
{
    protected static ?string $model = PindahBuku::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    protected static ?string $tenantRelationshipName = 'pindahBuku';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Pindah Buku';

    protected static ?string $modelLabel = 'Pindah Buku';

    protected static ?string $pluralModelLabel = 'Pindah Buku';

    public static function isScopedToTenant(): bool
    {
        return true;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('tanggal_mulai')
                    ->required()
                    ->live()
                    ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed')
                    ->label('Tanggal Mulai'),
                Forms\Components\DatePicker::make('tanggal_selesai')
                    ->required()
                    ->live()
                    ->afterOrEqual('tanggal_mulai')
                    ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed')
                    ->label('Tanggal Selesai'),
                Forms\Components\Select::make('periode_pembukuan_id')
                    ->label('Periode Pembukuan')
                    ->relationship(
                        name: 'periodePembukuan',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn ($query) => $query->where('relasi_bank_id', \Filament\Facades\Filament::getTenant()?->id)
                    )
                    ->getOptionLabelFromRecordUsing(function (\App\Models\PeriodePembukuan $record) {
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ];
                        $monthName = $months[$record->bulan] ?? "Bulan {$record->bulan}";
                        return "{$monthName} {$record->tahun} ({$record->status})";
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed'),
                Forms\Components\Select::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Closed' => 'Closed',
                    ])
                    ->default('Open')
                    ->required()
                    ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed')
                    ->label('Status'),
                Forms\Components\TextInput::make('keterangan')
                    ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed')
                    ->label('Keterangan')
                    ->maxLength(255),

                Section::make('Transaksi Terkait')
                    ->description('Daftar transaksi berstatus Validated yang masuk dalam periode pindah buku ini.')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Select::make('selected_transaksi')
                            ->label('Transaksi Terkait')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?PindahBuku $record) => $record?->status === 'Closed')
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, ?PindahBuku $record) {
                                if ($record && $record->exists) {
                                    $component->state($record->transaksi()->pluck('id')->toArray());
                                }
                            })
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get, ?PindahBuku $record) {
                                $tanggalMulai = $get('tanggal_mulai');
                                $tanggalSelesai = $get('tanggal_selesai');
                                if (! $tanggalMulai || ! $tanggalSelesai) {
                                    return [];
                                }
                                $tenantId = \Filament\Facades\Filament::getTenant()?->id;
                                return \App\Models\Transaksi::where('relasi_bank_id', $tenantId)
                                    ->whereBetween('tanggal_transaksi', [$tanggalMulai, $tanggalSelesai])
                                    ->where('status', 'Validated')
                                    ->whereNull('periode_pembukuan_id')
                                    ->where(function ($query) use ($record) {
                                        $query->whereNull('pindah_buku_id');
                                        if ($record && $record->exists) {
                                            $query->orWhere('pindah_buku_id', $record->id);
                                        }
                                    })
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [
                                        $t->id => sprintf(
                                            '%s | %s | Rp %s | %s',
                                            $t->tanggal_transaksi,
                                            $t->tipe_mutasi === 'D' ? 'Debit' : 'Kredit',
                                            number_format($t->nominal, 0, ',', '.'),
                                            $t->deskripsi
                                        )
                                    ]);
                            })
                            ->placeholder('Pilih transaksi...')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        $canTutupBuku = function (PindahBuku $record): bool {
            $user = Auth::user();

            if (! $user instanceof User) {
                return false;
            }

            return $user->can('tutupBuku', $record);
        };

        $canBukaBuku = function (PindahBuku $record): bool {
            $user = Auth::user();

            if (! $user instanceof User) {
                return false;
            }

            return $user->can('bukaBuku', $record);
        };

        return $table
            ->selectable()
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_mulai')
                    ->date()
                    ->sortable()
                    ->label('Tanggal Mulai'),
                Tables\Columns\TextColumn::make('tanggal_selesai')
                    ->date()
                    ->sortable()
                    ->label('Tanggal Selesai'),
                Tables\Columns\TextColumn::make('periodePembukuan')
                    ->formatStateUsing(function ($state, PindahBuku $record) {
                        $p = $record->periodePembukuan;
                        if (! $p) return '-';
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ];
                        return ($months[$p->bulan] ?? $p->bulan) . ' ' . $p->tahun;
                    })
                    ->label('Periode Buku'),
                Tables\Columns\TextColumn::make('keterangan')
                    ->searchable()
                    ->placeholder('-')
                    ->label('Keterangan'),
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
                    ->modalHeading('Tutup Buku & Posting Pindah Buku?')
                    ->modalDescription('Apakah Anda yakin ingin melakukan Tutup Buku & Posting? Aksi ini akan menghitung total debit/kredit transaksi berstatus Validated pada rentang tanggal terpilih, mengunci Pindah Buku, dan memperbarui transaksi menjadi Posted.')
                    ->modalSubmitActionLabel('Ya, Tutup Buku')
                    ->visible(fn (PindahBuku $record) => $record->status === 'Open' && $canTutupBuku($record))
                    ->action(function (PindahBuku $record) {
                        $record->tutupBukuDanPosting();

                        \Filament\Notifications\Notification::make()
                            ->title('Sukses')
                            ->body("Pindah Buku berhasil ditutup.")
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('bukaBuku')
                    ->label('Buka Kembali')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Buka Kembali Pindah Buku?')
                    ->modalDescription('Apakah Anda yakin ingin membuka kembali Pindah Buku ini? Status Pindah Buku akan kembali menjadi Open, seluruh transaksi terkait akan dikembalikan ke status Validated (Unpost), dan data dapat diedit kembali.')
                    ->modalSubmitActionLabel('Ya, Buka Kembali')
                    ->visible(fn (PindahBuku $record) => $record->status === 'Closed' && $canBukaBuku($record))
                    ->action(function (PindahBuku $record) {
                        $record->bukaKembaliDanUnpost();

                        \Filament\Notifications\Notification::make()
                            ->title('Sukses')
                            ->body("Pindah Buku berhasil dibuka kembali dan transaksi di-unpost.")
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
            'index' => Pages\ListPindahBukus::route('/'),
            'create' => Pages\CreatePindahBuku::route('/create'),
            'edit' => Pages\EditPindahBuku::route('/{record}/edit'),
        ];
    }
}
