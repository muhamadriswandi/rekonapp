<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPenerimaan extends Model
{
    protected $table = 'jenis_penerimaan';

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($jenisPenerimaan) {
            if (!blank($jenisPenerimaan->regex_pattern)) {
                $transactions = Transaksi::where('status', 'Raw')
                    ->doesntHave('rincian')
                    ->whereNotNull('deskripsi')
                    ->get();

                foreach ($transactions as $transaksi) {
                    if (Transaksi::matchesPattern($transaksi->deskripsi, $jenisPenerimaan->regex_pattern)) {
                        $transaksi->rincian()->create([
                            'jenis_penerimaan_id' => $jenisPenerimaan->id,
                            'nominal' => $transaksi->nominal,
                        ]);
                        $transaksi->update(['status' => 'Verified']);
                    }
                }
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(JenisPenerimaan::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(JenisPenerimaan::class, 'parent_id');
    }

    public function transaksiRincian()
    {
        return $this->hasMany(TransaksiRincian::class, 'jenis_penerimaan_id');
    }
}
