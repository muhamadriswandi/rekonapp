<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instansi extends Model
{
    protected $table = 'instansi';

    protected $guarded = [];

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'instansi_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'instansi_id');
    }

    public function relasiBank()
    {
        return $this->belongsToMany(RelasiBank::class, 'instansi_relasi_bank', 'instansi_id', 'relasi_bank_id');
    }
}
