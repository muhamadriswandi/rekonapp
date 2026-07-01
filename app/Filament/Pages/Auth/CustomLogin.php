<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;

class CustomLogin extends Login
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Select::make('tahun')
                    ->label('Tahun Anggaran')
                    ->options(function() {
                        $currentYear = now()->year;
                        $years = [];
                        for ($i = -2; $i <= 5; $i++) {
                            $y = $currentYear + $i;
                            $years[$y] = (string) $y;
                        }
                        return $years;
                    })
                    ->default(now()->year)
                    ->required(),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        // Get the form state containing the selected year
        $data = $this->form->getState();
        $year = $data['tahun'] ?? now()->year;

        // Save selected year into session
        session(['active_year' => $year]);

        return parent::authenticate();
    }
}
