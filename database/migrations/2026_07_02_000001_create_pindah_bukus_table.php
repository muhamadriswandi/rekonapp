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
        Schema::create('pindah_buku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relasi_bank_id')->constrained('relasi_bank')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan')->nullable();
            $table->decimal('total_debit', 20, 2)->default(0);
            $table->decimal('total_kredit', 20, 2)->default(0);
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->foreignId('pindah_buku_id')->nullable()->constrained('pindah_buku')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign(['pindah_buku_id']);
            $table->dropColumn('pindah_buku_id');
        });
        Schema::dropIfExists('pindah_buku');
    }
};
