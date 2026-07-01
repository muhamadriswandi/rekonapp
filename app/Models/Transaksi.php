<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';

    protected $guarded = [];

    protected static function booted()
    {
        static::created(function ($transaksi) {
            $transaksi->autoClassify();
        });
    }

    public function autoClassify()
    {
        if (blank($this->deskripsi)) {
            return;
        }

        // 1. Auto-tag payment channel if not set
        if (is_null($this->kanal_pembayaran_id)) {
            $kanals = KanalPembayaran::whereNotNull('regex_pattern')->get();
            foreach ($kanals as $kanal) {
                if (static::matchesPattern($this->deskripsi, $kanal->regex_pattern)) {
                    $this->update(['kanal_pembayaran_id' => $kanal->id]);
                    break;
                }
            }
        }

        // 2. Auto-tag revenue type if status is Raw and doesn't have rincian
        if ($this->status === 'Raw' && !$this->rincian()->exists()) {
            $jenises = JenisPenerimaan::whereNotNull('regex_pattern')->get();
            foreach ($jenises as $jenis) {
                if (static::matchesPattern($this->deskripsi, $jenis->regex_pattern)) {
                    $this->rincian()->create([
                        'jenis_penerimaan_id' => $jenis->id,
                        'nominal' => $this->nominal,
                    ]);
                    $this->update(['status' => 'Verified']);
                    break;
                }
            }
        }
    }

    public static function matchesPattern(string $text, string $pattern): bool
    {
        $text = trim($text);
        $pattern = trim($pattern);
        
        if (blank($text) || blank($pattern)) {
            return false;
        }
        
        if (str_starts_with($pattern, '/') && preg_match('/\/[a-z]*$/', $pattern)) {
            try {
                return (bool) @preg_match($pattern, $text);
            } catch (\Throwable $e) {
                // fall through
            }
        }
        
        $escaped = preg_quote($pattern, '/');
        return (bool) @preg_match('/' . $escaped . '/i', $text);
    }

    public function relasiBank()
    {
        return $this->belongsTo(RelasiBank::class, 'relasi_bank_id');
    }

    public function kanalPembayaran()
    {
        return $this->belongsTo(KanalPembayaran::class, 'kanal_pembayaran_id');
    }

    public function instansi()
    {
        return $this->belongsTo(Instansi::class, 'instansi_id');
    }

    public function periodePembukuan()
    {
        return $this->belongsTo(PeriodePembukuan::class, 'periode_pembukuan_id');
    }

    public function pindahBuku()
    {
        return $this->belongsTo(PindahBuku::class, 'pindah_buku_id');
    }

    public function rincian()
    {
        return $this->hasMany(TransaksiRincian::class, 'transaksi_id');
    }
}
