<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiRincian extends Model
{
    protected $table = 'transaksi_rincian';

    protected $guarded = [];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function jenisPenerimaan()
    {
        return $this->belongsTo(JenisPenerimaan::class, 'jenis_penerimaan_id');
    }
}
