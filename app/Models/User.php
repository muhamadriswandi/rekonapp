<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\HasTenants;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'instansi_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasTenants, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function instansi()
    {
        return $this->belongsTo(Instansi::class, 'instansi_id');
    }

    public function getTenants(Panel $panel): Collection
    {
        if (is_null($this->instansi_id)) {
            return RelasiBank::all();
        }
        return RelasiBank::whereHas('instansi', function ($query) {
            $query->where('instansi.id', $this->instansi_id);
        })->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (is_null($this->instansi_id)) {
            return true;
        }
        return $tenant->instansi()->where('instansi.id', $this->instansi_id)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
