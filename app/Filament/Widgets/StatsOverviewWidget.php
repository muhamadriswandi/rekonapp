<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Transaksi;
use Filament\Facades\Filament;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return [];
        }

        

        // Transaksi Raw (Butuh Tindakan)
        $rawCount = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Raw')
            ->count();

        $rawSum = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Raw')
            ->sum('nominal');
            
        // Total Tervalidasi
        $validatedCount = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Validated')
            ->count();

        $validatedSum = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Validated')
            ->sum('nominal');
    

        // Total Posting
        $postedCount = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Posted')
            ->count();

        $postedSum = Transaksi::where('relasi_bank_id', $tenant->id)
            ->where('status', 'Posted')
            ->sum('nominal');


        return [
            Stat::make('Total Tervalidasi', 'Rp ' . number_format($validatedSum, 2, ',', '.'))
                ->description($validatedCount . ' Transaksi berstatus Validated')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Transaksi Raw (Butuh Tindakan)', $rawCount . ' Transaksi')
                ->description('Total nominal: Rp ' . number_format($rawSum, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Total Posting', 'Rp ' . number_format($postedSum, 2, ',', '.'))
                ->description($postedCount . ' Transaksi berstatus Posted')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
