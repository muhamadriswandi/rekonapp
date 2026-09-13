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

    public function periodePembukuan()
    {
        return $this->belongsTo(PeriodePembukuan::class, 'periode_pembukuan_id');
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'pindah_buku_id');
    }

    public function recalculateTotals(): void
    {
        $transactions = $this->transaksi()->get();
        $this->updateQuietly([
            'total_debit' => $transactions->where('tipe_mutasi', 'D')->sum('nominal'),
            'total_kredit' => $transactions->where('tipe_mutasi', 'K')->sum('nominal'),
        ]);
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

            // Bulk update transactions status to Posted and set periode_pembukuan_id
            if ($transactions->isNotEmpty()) {
                $updateData = [
                    'status' => 'Posted',
                ];

                if ($this->periode_pembukuan_id) {
                    $updateData['periode_pembukuan_id'] = $this->periode_pembukuan_id;
                }

                Transaksi::whereIn('id', $transactions->pluck('id'))
                    ->update($updateData);

                // If associated with a PeriodePembukuan, recalculate its totals
                if ($this->periode_pembukuan_id) {
                    $period = $this->periodePembukuan()->first();
                    if ($period) {
                        $allPeriodTransactions = Transaksi::where('periode_pembukuan_id', $period->id)
                            ->where('status', 'Posted')
                            ->get();
                        $period->updateQuietly([
                            'total_debit' => $allPeriodTransactions->where('tipe_mutasi', 'D')->sum('nominal'),
                            'total_kredit' => $allPeriodTransactions->where('tipe_mutasi', 'K')->sum('nominal'),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Re-open the pindah buku transfer and unpost transactions back to Validated.
     */
    public function bukaKembaliDanUnpost(): void
    {
        DB::transaction(function () {
            // Find all transactions associated with this PindahBuku that are Posted
            $transactions = Transaksi::where('pindah_buku_id', $this->id)
                ->where('status', 'Posted')
                ->get();

            $oldPeriodId = $this->periode_pembukuan_id;

            // Rollback transactions to Validated and clear periode_pembukuan_id
            if ($transactions->isNotEmpty()) {
                Transaksi::whereIn('id', $transactions->pluck('id'))
                    ->update([
                        'status' => 'Validated',
                        'periode_pembukuan_id' => null,
                    ]);
            }

            // Update PindahBuku record
            $this->update([
                'status' => 'Open',
                'closed_at' => null,
            ]);

            // If associated with a PeriodePembukuan, recalculate its totals
            if ($oldPeriodId) {
                $period = PeriodePembukuan::find($oldPeriodId);
                if ($period) {
                    $allPeriodTransactions = Transaksi::where('periode_pembukuan_id', $period->id)
                        ->where('status', 'Posted')
                        ->get();
                    $period->updateQuietly([
                        'total_debit' => $allPeriodTransactions->where('tipe_mutasi', 'D')->sum('nominal'),
                        'total_kredit' => $allPeriodTransactions->where('tipe_mutasi', 'K')->sum('nominal'),
                    ]);
                }
            }
        });
    }
}
