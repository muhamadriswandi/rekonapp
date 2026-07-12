<?php

namespace App\Providers\Filament;

use App\Models\RelasiBank;
use App\Http\Controllers\LaporanKonsolidasiController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Enums\Width;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->tenant(RelasiBank::class)
            ->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
            ->scopeToTenant(false))
            ->resourceEditPageRedirect('index')
            ->maxContentWidth(Width::Full)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->tenantRoutes(function () {
                \Illuminate\Support\Facades\Route::get('/reports/laporan-harian', [\App\Http\Controllers\LaporanHarianController::class, 'downloadPdf'])
                    ->name('reports.laporan-harian');
                \Illuminate\Support\Facades\Route::get('/reports/laporan-penerimaan', [\App\Http\Controllers\LaporanPenerimaanController::class, 'downloadPdf'])
                    ->name('reports.laporan-penerimaan');
            })
            ->routes(function () {
                \Illuminate\Support\Facades\Route::get('/reports/laporan-konsolidasi', [LaporanKonsolidasiController::class, 'downloadPdf'])
                    ->name('reports.laporan-konsolidasi');
                \Illuminate\Support\Facades\Route::get('/reports/laporan-konsolidasi-excel', [LaporanKonsolidasiController::class, 'downloadExcel'])
                    ->name('reports.laporan-konsolidasi-excel');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                //
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
