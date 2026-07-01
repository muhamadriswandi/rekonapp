Product Requirements Document (PRD)

Nama Produk: Aplikasi Rekening Koran (RekorApp)
Fokus Utama: Efisiensi Pemrosesan Transaksi Keuangan & Rekonsiliasi
Stack Teknologi: Laravel 13, FilamentPHP 5 (Native Admin Panel), MySQL
Versi Dokumen: 5.0 (Native Filament Implementation & Multi-Bank)

1. Ringkasan Eksekutif

RekorApp adalah sistem manajemen rekening koran internal yang dirancang untuk memproses, memverifikasi, memvalidasi, dan membukukan transaksi keuangan harian. Aplikasi ini dibangun sepenuhnya menggunakan Native FilamentPHP Admin Panel untuk memaksimalkan kecepatan pengembangan (Rapid Application Development). Sistem mengutamakan fungsionalitas pengolahan data massal, multi-tenancy untuk pemisahan bank, dan keandalan impor data tanpa interupsi error.

2. Tujuan & Sasaran

Efisiensi Pengembangan: Menggunakan Table Builder, Form Builder, dan Action Modals bawaan Filament secara murni tanpa override CSS yang kompleks.

Keandalan Impor Data (Fault Tolerance): Mampu mengimpor file CSV mentah tanpa terblokir oleh strict validation. Baris yang kosong atau tidak dikenali akan tetap masuk ke sistem dengan status "Raw" untuk diperbaiki nanti, mencegah Server Error.

Akurasi & Pencarian: Menyediakan filter berbasis rentang periode/bulan (month/period range filter) di tabel untuk meningkatkan akurasi pencocokan data rekonsiliasi.

Multi-Bank Management: Mengisolasi data transaksi berdasarkan relasi bank menggunakan fitur Multi-Tenancy bawaan Filament.

3. Desain UI/UX: Native Filament Admin

Aplikasi akan menggunakan tema standar (default theme) dari FilamentPHP v5:

Layout: Standar admin panel dengan sidebar kiri untuk navigasi dan topbar untuk Tenant Switcher (pilihan Bank).

Komponen: Menggunakan Card, Table, dan Modal standar dari pustaka UI Filament.

Warna Tema: Mengonfigurasi PanelProvider dengan warna primary default atau warna yang merepresentasikan institusi keuangan terkait.

Dashboard: Menggunakan StatsOverviewWidget standar yang disediakan Filament untuk merangkum total saldo, transaksi tervalidasi, dan transaksi yang butuh tindakan.

4. Alur Kerja Pengguna & Interaksi UI

Navigasi Multi-Bank: Operator memilih Bank (misal: PDRD, PBB) melalui dropdown Tenant Switcher di topbar. Sistem otomatis memfilter seluruh data tabel berdasarkan relasi bank tersebut.

Fase Upload Data (Bypass Validation):

Di halaman Transaksi, operator menekan Header Action "Upload CSV".

Importer akan menyimpan data apa adanya ke tabel database. Cell kosong atau format yang tidak standar tidak boleh memicu Server Error. Sistem mengklasifikasikan data bermasalah ini dengan status Raw.

Fase Verifikasi & Rincian (Split Transaksi): * Untuk memecah transaksi, operator menggunakan Row Action "Rincian" pada tabel.

Muncul Standard Modal dari Filament berisi komponen Repeater.

Operator menambahkan baris rincian jenis penerimaan. Validasi custom memastikan total rincian sama dengan total transaksi sebelum bisa disimpan.

Fase Validasi (Bulk Action):

Operator menggunakan filter bulan/rentang waktu (Date Range Filter / Select Filter Bulan) pada tabel untuk memunculkan transaksi di periode tertentu.

Operator mencentang beberapa baris dan menggunakan Bulk Action "Set Instansi" untuk memperbarui data secara massal. Status berubah menjadi Validated.

5. Spesifikasi Modul Teknis Filament

Modul Impor (Filament Excel): Menggunakan plugin seperti pboivin/filament-excel-actions atau importer native Filament. Harus diatur agar menangkap exception pada baris CSV yang rusak dan melewatinya dengan nilai default/null agar proses impor tetap selesai.

Tabel Transaksi (TransaksiResource):

Filters: Terapkan Filter::make('tanggal') menggunakan komponen form DatePicker dengan mode range(), atau gunakan SelectFilter untuk memilih periode pembukuan spesifik.

Actions: Gunakan Action::make('rincian') yang menampilkan form modal bawaan.

Kategori Induk-Anak: Menggunakan struktur Self-Referencing. Pada Filament Form, gunakan komponen Select dengan modifikasi options() atau relasi berjenjang agar Kategori Induk tidak bisa dipilih langsung sebagai transaksi rincian.

6. Skema Database (MySQL)

-- Tabel Bank (Tenant)
CREATE TABLE relasi_bank (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    kode_bank VARCHAR(50) UNIQUE,
    nama_bank VARCHAR(255)
);

-- Tabel Referensi Jenis Penerimaan (Parent-Child)
CREATE TABLE jenis_penerimaan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT NULL,
    kode VARCHAR(50) UNIQUE,
    nama VARCHAR(255),
    regex_pattern VARCHAR(255) NULL,
    FOREIGN KEY (parent_id) REFERENCES jenis_penerimaan(id) ON DELETE SET NULL
);

CREATE TABLE kanal_pembayaran (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) UNIQUE,
    nama VARCHAR(255),
    regex_pattern VARCHAR(255) NULL
);

CREATE TABLE instansi (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    kode_instansi VARCHAR(50) UNIQUE,
    nama_instansi VARCHAR(255)
);

CREATE TABLE periode_pembukuan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    relasi_bank_id BIGINT NOT NULL,
    bulan INT,
    tahun INT,
    total_debit DECIMAL(20,2) DEFAULT 0,
    total_kredit DECIMAL(20,2) DEFAULT 0,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (relasi_bank_id) REFERENCES relasi_bank(id)
);

-- Tabel Transaksi Utama
CREATE TABLE transaksi (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    relasi_bank_id BIGINT NOT NULL,
    tanggal_transaksi DATE NULL, -- Boleh NULL untuk antisipasi error import CSV
    deskripsi TEXT NULL,         -- Boleh NULL untuk mengakomodasi raw data
    nominal DECIMAL(20, 2) DEFAULT 0,
    tipe_mutasi ENUM('D', 'K') NULL,
    
    kanal_pembayaran_id BIGINT NULL,
    instansi_id BIGINT NULL,
    periode_pembukuan_id BIGINT NULL,
    
    status ENUM('Raw', 'Verified', 'Validated', 'Posted') DEFAULT 'Raw',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (relasi_bank_id) REFERENCES relasi_bank(id),
    FOREIGN KEY (kanal_pembayaran_id) REFERENCES kanal_pembayaran(id),
    FOREIGN KEY (instansi_id) REFERENCES instansi(id),
    FOREIGN KEY (periode_pembukuan_id) REFERENCES periode_pembukuan(id)
);

-- Tabel Transaksi Rincian (1-to-Many)
CREATE TABLE transaksi_rincian (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id BIGINT NOT NULL,
    jenis_penerimaan_id BIGINT NOT NULL,
    nominal DECIMAL(20, 2) NOT NULL,
    
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (jenis_penerimaan_id) REFERENCES jenis_penerimaan(id)
);


7. Keamanan & Arsitektur

Otentikasi: Native Filament Auth.

Otorisasi Role: Menggunakan Spatie Permission Plugin.

Multi-Tenancy Setup: Menerapkan Filament Multi-tenancy dengan RelasiBank sebagai model Tenant. Pemfilteran data secara otomatis ditangani oleh sistem Global Scope Filament, memastikan isolasi data mutlak antar bank.

8. Optimasi Performa (Laravel Boost Settings)

Mengingat aplikasi ini memproses ribuan baris data transaksi via file CSV dan merender tabel data berukuran besar di Filament, pengaturan performa berikut sangat disarankan untuk diterapkan pada level framework:

Background Job Processing (Queue):

Pindahkan proses import CSV dan auto-tagging (verifikasi) ke antrean belakang (background jobs).

Gunakan Redis sebagai QUEUE_CONNECTION di file .env (hindari penggunaan driver sync atau database untuk operasional production berskala besar).

Database Indexing:

Pastikan index MySQL ditambahkan pada kolom yang sering digunakan untuk memfilter dan mengelompokkan data di Filament, yaitu: relasi_bank_id, status, dan tanggal_transaksi pada tabel transaksi.

Filament Cache Optimization:

Saat deployment ke production, jalankan perintah caching spesifik Filament untuk menghemat waktu compile antarmuka:

php artisan filament:cache-components

php artisan icons:cache

Laravel Octane (Opsional tapi Kuat):

Untuk mem-boost waktu load halaman admin panel secara drastis, instal Laravel Octane dengan server Swoole atau FrankenPHP. Ini akan menyimpan framework Laravel di dalam memori (RAM), menghilangkan proses booting di setiap request halaman Filament.

Standard Laravel Boost:

Selalu jalankan kombo optimasi ini di production:

php artisan optimize

php artisan config:cache

php artisan event:cache

php artisan view:cache

9. Ekosistem Eksternal (Library Wajib)

Untuk memaksimalkan fungsionalitas rekon ini, sangat disarankan untuk mengintegrasikan library pihak ketiga berikut:

Filament Excel (pboivin/filament-excel-actions / maatwebsite/excel):
Wajib diinstal untuk menangani proses import/export CSV di Filament Admin Panel. Library ini menangani parsing struktur baris Excel/CSV dan membantu proses mapping field ke model Eloquent dengan lebih aman dan terstruktur dibandingkan menggunakan helper bawaan Laravel.

 Laravel Livewire Tables (kulkul22/laravel-livewire-tables / pmaffettone/livewire-tables):
Sangat direkomendasikan sebagai pengganti TableResource bawaan dari Filament (jika dirasa kurang fleksibel untuk data masif/transaksional kompleks). Library ini menawarkan performa yang lebih baik saat menangani pagination data besar dan memiliki fitur bawaan untuk filter dan bulk action yang sangat cepat.

Laravel Scout (Opsional):
Untuk menyediakan fitur pencarian teks penuh (full-text search) pada deskripsi transaksi yang sangat panjang, menggantikan pencarian SQL LIKE %...% yang lambat di database besar.