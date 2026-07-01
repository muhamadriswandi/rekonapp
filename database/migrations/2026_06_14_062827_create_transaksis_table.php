<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relasi_bank_id')->constrained('relasi_bank')->cascadeOnDelete();
            $table->date('tanggal_transaksi')->nullable();
            $table->text('deskripsi')->nullable();
            $table->decimal('nominal', 20, 2)->default(0);
            $table->enum('tipe_mutasi', ['D', 'K'])->nullable();
            
            $table->foreignId('kanal_pembayaran_id')->nullable()->constrained('kanal_pembayaran')->nullOnDelete();
            $table->foreignId('instansi_id')->nullable()->constrained('instansi')->nullOnDelete();
            $table->foreignId('periode_pembukuan_id')->nullable()->constrained('periode_pembukuan')->nullOnDelete();
            
            $table->enum('status', ['Raw', 'Verified', 'Validated', 'Posted'])->default('Raw');
            $table->timestamps();

            // Indexes for optimizing Filament filters and sorting
            $table->index('relasi_bank_id');
            $table->index('status');
            $table->index('tanggal_transaksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
