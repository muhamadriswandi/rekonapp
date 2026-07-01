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
        Schema::create('jenis_penerimaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('jenis_penerimaan')->nullOnDelete();
            $table->string('kode', 50)->unique();
            $table->string('nama', 255);
            $table->string('regex_pattern', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_penerimaan');
    }
};
