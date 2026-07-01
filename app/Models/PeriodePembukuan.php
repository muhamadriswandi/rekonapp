<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PeriodePembukuan extends Model
{
    protected $table = 'periode_pembukuan';

    protected $guarded = [];

    public function relasiBank()
    {
        return $this->belongsTo(RelasiBank::class, 'relasi_bank_id');
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'periode_pembukuan_id');
    }

    /**
     * Close the booking period and post validated transactions.
     */
    public function tutupBukuDanPosting(): void
    {
        DB::transaction(function () {
            // Find all transactions in this RelasiBank on the month/year that are Validated
            $transactions = Transaksi::where('relasi_bank_id', $this->relasi_bank_id)
                ->whereMonth('tanggal_transaksi', $this->bulan)
                ->whereYear('tanggal_transaksi', $this->tahun)
                ->where('status', 'Validated')
                ->get();

            // Calculate total debit and credit
            $totalDebit = $transactions->where('tipe_mutasi', 'D')->sum('nominal');
            $totalKredit = $transactions->where('tipe_mutasi', 'K')->sum('nominal');

            // Update this PeriodePembukuan record
            $this->update([
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'status' => 'Closed',
                'closed_at' => now(),
            ]);

            // Bulk update transactions: set periode_pembukuan_id and status to Posted
            if ($transactions->isNotEmpty()) {
                Transaksi::whereIn('id', $transactions->pluck('id'))
                    ->update([
                        'periode_pembukuan_id' => $this->id,
                        'status' => 'Posted',
                    ]);
            }
        });
    }
}
