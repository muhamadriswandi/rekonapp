<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Header Banner -->
        <div class="relative overflow-hidden bg-gradient-to-r from-amber-500 via-amber-600 to-orange-600 rounded-3xl p-8 shadow-lg text-white">
            <div class="relative z-10 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-xs font-semibold backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-book-open" class="w-4 h-4" />
                    Pusat Bantuan
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight">Panduan Penggunaan RekonApp</h2>
                <p class="text-amber-50 text-sm max-w-2xl leading-relaxed">
                    Dokumentasi interaktif untuk membantu Anda memahami seluruh fitur rekonsiliasi keuangan. Pelajari alur transaksi bank, mekanisme tutup buku bulanan, pemindahan buku kustom, hingga pencetakan laporan.
                </p>
            </div>
            <div class="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -left-10 -top-10 w-32 h-32 rounded-full bg-amber-400/20 blur-xl"></div>
        </div>

        <!-- Grid of Guide Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Card 1: Alur Rekonsiliasi -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Alur Status Transaksi</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Siklus hidup data transaksi bank yang diproses secara bertahap oleh sistem:
                    </p>
                    <div class="space-y-3 pt-2">
                        <div class="flex items-start gap-2.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xxs font-bold mt-0.5">1</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300"><strong class="text-gray-900 dark:text-white">Raw:</strong> Data mutasi awal hasil impor CSV.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 text-xxs font-bold mt-0.5">2</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300"><strong class="text-gray-900 dark:text-white">Verified:</strong> Rincian penerimaan terklasifikasi otomatis.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 text-xxs font-bold mt-0.5">3</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300"><strong class="text-gray-900 dark:text-white">Validated:</strong> Data transaksi telah diperiksa & divalidasi.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 text-xxs font-bold mt-0.5">4</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300"><strong class="text-gray-900 dark:text-white">Posted:</strong> Dikunci permanen via menu Tutup Buku.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Transaksi & Impor -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Transaksi & Impor CSV</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Cara mengunggah mutasi rekening koran baru ke dalam sistem rekon:
                    </p>
                    <ol class="list-decimal list-inside text-xs text-gray-600 dark:text-gray-300 space-y-2.5 pt-2">
                        <li>Buka halaman menu <span class="font-semibold text-gray-900 dark:text-white">Daftar Transaksi</span>.</li>
                        <li>Klik tombol <span class="font-semibold text-gray-900 dark:text-white">Import Transaksi CSV</span> di kanan atas.</li>
                        <li>Pilih file CSV mutasi rekening bank Anda.</li>
                        <li>Klik <span class="font-semibold text-gray-900 dark:text-white">Proses Impor</span>. Sistem akan otomatis mendeteksi kanal & jenis penerimaan yang cocok.</li>
                    </ol>
                </div>
            </div>

            <!-- Card 3: Tutup Buku Bulanan -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400">
                        <x-filament::icon icon="heroicon-o-calendar" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Tutup Buku Bulanan</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Mengunci pembukuan transaksi secara konsolidasi per bulan kalender penuh:
                    </p>
                    <ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-300 space-y-2.5 pt-2">
                        <li>Buat entri periode di menu <span class="font-semibold text-gray-900 dark:text-white">Periode Pembukuan</span>.</li>
                        <li>Tentukan Bulan, Tahun, dan atur status ke **Open**.</li>
                        <li>Sistem otomatis menjumlahkan nominal debit & kredit yang valid.</li>
                        <li>Klik aksi <span class="font-semibold text-amber-600 dark:text-amber-400">Tutup Buku & Posting</span> untuk mengunci seluruh transaksi menjadi status **Posted**.</li>
                    </ul>
                </div>
            </div>

            <!-- Card 4: Pindah Buku (Kustom) -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-950/30 text-orange-600 dark:text-orange-400">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Pindah Buku (Kustom)</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Penutupan pembukuan dengan rentang tanggal kustom dan pilihan transaksi manual:
                    </p>
                    <ol class="list-decimal list-inside text-xs text-gray-600 dark:text-gray-300 space-y-2.5 pt-2">
                        <li>Masuk ke menu <span class="font-semibold text-gray-900 dark:text-white">Pindah Buku</span> dan klik **Buat**.</li>
                        <li>Tentukan rentang tanggal mulai & selesai.</li>
                        <li>Pada bagian **Transaksi Terkait**, pilih transaksi mana saja yang ingin dimasukkan ke dalam buku transfer ini.</li>
                        <li>Simpan, lalu klik aksi <span class="font-semibold text-amber-600 dark:text-amber-400">Tutup Buku & Posting</span> untuk menguncinya.</li>
                    </ol>
                </div>
            </div>

            <!-- Card 5: Laporan & Cetak PDF -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400">
                        <x-filament::icon icon="heroicon-o-document-chart-bar" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Laporan & Cetak PDF</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Memantau laporan keuangan real-time serta mengunduh berkas laporan resmi:
                    </p>
                    <div class="space-y-2 pt-2">
                        <div class="text-xs text-gray-600 dark:text-gray-300">
                            <span class="font-semibold text-gray-900 dark:text-white">Laporan Harian:</span> Menyajikan ikhtisar harian instansi & matriks penerimaan vs kanal pembayaran.
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">
                            <span class="font-semibold text-gray-900 dark:text-white">Laporan Penerimaan:</span> Menyajikan rincian data berstatus **Posted** yang dikelompokkan per jenis penerimaan lengkap dengan subtotal.
                        </div>
                        <div class="text-xs text-amber-700 dark:text-amber-400 font-medium">
                            * Klik tombol **Cetak PDF** untuk mengunduh laporan berformat PDF resmi.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 6: Konfigurasi Master Data -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400">
                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Konfigurasi Master Data</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Mengatur data parameter penunjang otomatisasi sistem rekon:
                    </p>
                    <div class="grid grid-cols-2 gap-2.5 pt-2">
                        <div class="bg-gray-50 dark:bg-gray-800/40 p-2 rounded-lg border border-gray-100 dark:border-gray-850">
                            <span class="block font-bold text-gray-900 dark:text-white text-xxs uppercase">Kanal</span>
                            <span class="text-xxs text-gray-500 dark:text-gray-400">Pola Regex pendeteksi metode bayar.</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800/40 p-2 rounded-lg border border-gray-100 dark:border-gray-850">
                            <span class="block font-bold text-gray-900 dark:text-white text-xxs uppercase">Jenis Penerimaan</span>
                            <span class="text-xxs text-gray-500 dark:text-gray-400">Hierarki kategori pajak & retribusi.</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800/40 p-2 rounded-lg border border-gray-100 dark:border-gray-850">
                            <span class="block font-bold text-gray-900 dark:text-white text-xxs uppercase">Instansi</span>
                            <span class="text-xxs text-gray-500 dark:text-gray-400">Pendaftaran dinas/badan pengelola.</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800/40 p-2 rounded-lg border border-gray-100 dark:border-gray-850">
                            <span class="block font-bold text-gray-900 dark:text-white text-xxs uppercase">User & Role</span>
                            <span class="text-xxs text-gray-500 dark:text-gray-400">Pengaturan akses Supervisor/Operator.</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>
