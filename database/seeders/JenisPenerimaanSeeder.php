<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPenerimaanSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = base_path('Jenis Penerimaan.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("File CSV tidak ditemukan: {$csvPath}");
            return;
        }

        $file = fopen($csvPath, 'r');

        // Skip header baris pertama
        fgetcsv($file, 0, ';');

        $rows = [];
        while (($line = fgetcsv($file, 0, ';')) !== false) {
            if (count($line) < 2) continue;

            $kode           = trim($line[0] ?? '');
            $nama           = trim($line[1] ?? '');
            $kategoriInduk  = trim($line[2] ?? '');

            if (empty($kode) || empty($nama)) continue;

            $rows[] = [
                'kode'          => $kode,
                'nama'          => $nama,
                'kategori_induk'=> $kategoriInduk,
            ];
        }

        fclose($file);

        // ── PASS 1: Insert semua record tanpa parent_id ──────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('jenis_penerimaan')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($rows as $row) {
            DB::table('jenis_penerimaan')->insert([
                'kode'       => $row['kode'],
                'nama'       => $row['nama'],
                'parent_id'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Pass 1 selesai: ' . count($rows) . ' record dimasukkan.');

        // ── PASS 2: Update parent_id berdasarkan nama Kategori Induk ─────────
        $updated = 0;
        foreach ($rows as $row) {
            if (empty($row['kategori_induk'])) continue;

            $parent = DB::table('jenis_penerimaan')
                ->where('nama', $row['kategori_induk'])
                ->first();

            if (! $parent) {
                $this->command->warn("Parent tidak ditemukan untuk '{$row['nama']}' → kategori induk: '{$row['kategori_induk']}'");
                continue;
            }

            DB::table('jenis_penerimaan')
                ->where('kode', $row['kode'])
                ->update(['parent_id' => $parent->id]);

            $updated++;
        }

        $this->command->info("Pass 2 selesai: {$updated} record diperbarui parent_id-nya.");
    }
}
