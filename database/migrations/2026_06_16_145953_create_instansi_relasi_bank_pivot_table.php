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
        // Remove instansi_id from relasi_bank
        Schema::table('relasi_bank', function (Blueprint $table) {
            $table->dropForeign(['instansi_id']);
            $table->dropColumn('instansi_id');
        });

        // Create pivot table
        Schema::create('instansi_relasi_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->foreignId('relasi_bank_id')->constrained('relasi_bank')->cascadeOnDelete();
            $table->unique(['instansi_id', 'relasi_bank_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instansi_relasi_bank');

        Schema::table('relasi_bank', function (Blueprint $table) {
            $table->foreignId('instansi_id')->nullable()->constrained('instansi')->nullOnDelete();
        });
    }
};
