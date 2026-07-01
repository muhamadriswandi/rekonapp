<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PindahBuku extends Model
{
    protected $table = 'pindah_buku';

    protected $guarded = [];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'closed_at' => 'datetime',
    ];

    public function relasiBank()
    {
        return $this->belongsTo(RelasiBank::class, 'relasi_bank_id');
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'pindah_buku_id');
    }

    /**
     * Close the pindah buku transfer and post validated transactions.
     */
    public function tutupBukuDanPosting(): void
    {
        DB::transaction(function () {
            // Find all transactions associated with this PindahBuku that are Validated
            $transactions = Transaksi::where('pindah_buku_id', $this->id)
                ->where('status', 'Validated')
                ->get();

            // Calculate total debit and credit
            $totalDebit = $transactions->where('tipe_mutasi', 'D')->sum('nominal');
            $totalKredit = $transactions->where('tipe_mutasi', 'K')->sum('nominal');

            // Update this PindahBuku record
            $this->update([
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'status' => 'Closed',
                'closed_at' => now(),
            ]);

            // Bulk update transactions status to Posted
            if ($transactions->isNotEmpty()) {
                Transaksi::whereIn('id', $transactions->pluck('id'))
                    ->update([
                        'status' => 'Posted',
                    ]);
            }
        });
    }
}
