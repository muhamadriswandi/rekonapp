<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;

class RelasiBank extends Model implements HasName
{
    protected $table = 'relasi_bank';

    protected $guarded = [];

    public function getFilamentName(): string
    {
        return $this->nama_bank;
    }

    public function instansi()
    {
        return $this->belongsToMany(Instansi::class, 'instansi_relasi_bank', 'relasi_bank_id', 'instansi_id');
    }

    public function periodePembukuan()
    {
        return $this->hasMany(PeriodePembukuan::class, 'relasi_bank_id');
    }

    public function pindahBuku()
    {
        return $this->hasMany(PindahBuku::class, 'relasi_bank_id');
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'relasi_bank_id');
    }
}
