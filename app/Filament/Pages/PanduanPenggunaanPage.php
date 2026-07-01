<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PanduanPenggunaanPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Panduan Penggunaan';

    protected static ?string $title = 'Panduan Penggunaan';

    protected string $view = 'filament.pages.panduan-penggunaan-page';

    public static function isScopedToTenant(): bool
    {
        return true;
    }
}
