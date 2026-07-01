<?php

use App\Models\User;
use App\Models\RelasiBank;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use App\Filament\Pages\PanduanPenggunaanPage;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = RelasiBank::create([
        'kode_bank' => 'TEST_BANK',
        'nama_bank' => 'Test Bank'
    ]);

    $role = Role::create(['name' => 'Supervisor']);
    
    $this->user = User::create([
        'name' => 'Supervisor User',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password')
    ]);
    $this->user->assignRole($role);
    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    session(['active_year' => 2026]);
});

test('panduan penggunaan page renders successfully', function () {
    Livewire::test(PanduanPenggunaanPage::class)
        ->assertStatus(200);
});
