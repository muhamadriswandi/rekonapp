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
use Leek\FilamentRightClick\FilamentRightClickPlugin;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Pages\Auth\CustomLogin::class)
            ->tenant(RelasiBank::class)
            ->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                ->scopeToTenant(false))
            ->plugin(FilamentRightClickPlugin::make())
            ->resourceEditPageRedirect('index')
            ->maxContentWidth(Width::Full)
            ->font('Plus Jakarta Sans')
            ->sidebarCollapsibleOnDesktop()
            ->brandName("Pipakatan")
            ->colors([
                'primary' => Color::rgb('rgb(14, 42, 71)'),    // Primary Deep Navy
                'secondary' => Color::rgb('rgb(30, 76, 124)'), // Secondary Mid Navy
                'warning' => Color::rgb('rgb(201, 162, 39)'),  // Accent Rich Gold
                'gray' => Color::rgb('rgb(242, 239, 232)'),    // Neutral Warm Alabaster
                'info' => Color::rgb('rgb(30, 76, 124)'),
                'success' => Color::Emerald,
                'danger' => Color::Rose,
                ''
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
                \Illuminate\Support\Facades\Route::get('/reports/laporan-etpd', [\App\Http\Controllers\LaporanEtpdController::class, 'downloadPdf'])
                    ->name('reports.laporan-etpd');
                \Illuminate\Support\Facades\Route::get('/reports/laporan-etpd-excel', [\App\Http\Controllers\LaporanEtpdController::class, 'downloadExcel'])
                    ->name('reports.laporan-etpd-excel');
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
