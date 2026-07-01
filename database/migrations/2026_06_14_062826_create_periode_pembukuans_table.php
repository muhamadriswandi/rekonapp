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
        Schema::create('periode_pembukuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relasi_bank_id')->constrained('relasi_bank')->cascadeOnDelete();
            $table->integer('bulan');
            $table->integer('tahun');
            $table->decimal('total_debit', 20, 2)->default(0);
            $table->decimal('total_kredit', 20, 2)->default(0);
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periode_pembukuan');
    }
};
