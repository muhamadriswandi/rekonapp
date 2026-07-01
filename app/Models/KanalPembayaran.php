<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanalPembayaran extends Model
{
    protected $table = 'kanal_pembayaran';

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($kanalPembayaran) {
            if (!blank($kanalPembayaran->regex_pattern)) {
                $transactions = Transaksi::whereNull('kanal_pembayaran_id')
                    ->whereNotNull('deskripsi')
                    ->get();

                foreach ($transactions as $transaksi) {
                    if (Transaksi::matchesPattern($transaksi->deskripsi, $kanalPembayaran->regex_pattern)) {
                        $transaksi->update(['kanal_pembayaran_id' => $kanalPembayaran->id]);
                    }
                }
            }
        });
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'kanal_pembayaran_id');
    }
}
