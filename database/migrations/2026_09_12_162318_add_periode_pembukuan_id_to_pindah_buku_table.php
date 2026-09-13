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
        Schema::table('pindah_buku', function (Blueprint $table) {
            $table->foreignId('periode_pembukuan_id')->nullable()->after('relasi_bank_id')->constrained('periode_pembukuan')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pindah_buku', function (Blueprint $table) {
            $table->dropForeign(['periode_pembukuan_id']);
            $table->dropColumn('periode_pembukuan_id');
        });
    }
};
