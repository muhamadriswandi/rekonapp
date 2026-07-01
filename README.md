# RekonApp - Sistem Rekonsiliasi Rekening Koran Keuangan Daerah

RekonApp adalah aplikasi web berbasis **Laravel 11** dan **Filament v5 (Experimental/Schemas)** yang dirancang untuk melakukan proses rekonsiliasi dan pemindahbukuan mutasi rekening koran bank secara multi-tenant (Relasi Bank) secara otomatis dan terstruktur.

---

## 🚀 Fitur Utama

### 1. Multi-Tenant (Relasi Bank)
- Sistem mendukung multi-tenant berdasarkan bank mitra pengelola kas daerah. Setiap data transaksi, periode, dan laporan terisolasi dengan aman per Relasi Bank.

### 2. Impor & Klasifikasi Transaksi Otomatis
- **Impor CSV**: Memungkinkan operator mengunggah file mutasi rekening koran bank (CSV).
- **Otomatisasi Status**:
  - `Raw`: Transaksi mentah pasca impor.
  - `Verified`: Sistem mendeteksi **Kanal Pembayaran** & **Jenis Penerimaan** secara otomatis melalui pola *Regex* yang dikonfigurasi, lalu membuat entri rincian nominal secara otomatis.
  - `Validated`: Transaksi yang sudah ditinjau dan divalidasi oleh operator.
  - `Posted`: Transaksi yang sudah masuk dalam proses Tutup Buku (terkunci).

### 3. Tutup Buku Bulanan (Periode Pembukuan)
- Fasilitas penutupan buku transaksi secara berkala setiap bulan kalender penuh. Mengunci seluruh transaksi berstatus `Validated` pada bulan tersebut menjadi status `Posted`.

### 4. Pindah Buku Kustom (Pindah Buku)
- Solusi untuk rekening koran yang dipindahkan bukukan tidak berdasarkan awal/akhir bulan kalender.
- Memungkinkan penutupan buku dengan **rentang tanggal kustom**.
- Mendukung **pemilihan transaksi secara manual** (memilih transaksi mana yang diikutsertakan dan mana yang ditinggalkan meskipun tanggal transaksinya sama).

### 5. Laporan Keuangan & Cetak PDF Resmi
- **Laporan Harian**: Ikhtisar harian penerimaan per instansi dan matriks Jenis Penerimaan vs Kanal Pembayaran. Dilengkapi ekspor berkas PDF formal.
- **Laporan Penerimaan**: Laporan detail transaksi terposting (`Posted`) yang dikelompokkan berdasarkan **Jenis Penerimaan**, diurutkan kronologis tanggal, dilengkapi subtotal per kelompok, dan tombol **Cetak PDF**.

### 6. Pusat Bantuan (Panduan Penggunaan)
- Halaman dokumentasi terintegrasi di panel admin yang dirancang menggunakan kartu grid Tailwind CSS interaktif untuk memudahkan pemahaman seluruh alur sistem.

---

## 🛠️ Tech Stack & Requirements

- **PHP** >= 8.2 (Tested on PHP 8.4)
- **Laravel** 11.x
- **Filament** v5 (experimental schemas branch)
- **Database**: MySQL / SQLite (untuk pengujian)
- **DomPDF** (untuk pencetakan PDF)
- **Pest PHP** (untuk testing)

---

## ⚙️ Instalasi & Konfigurasi

### 1. Clone & Install Dependencies
```bash
git clone <url_repo>
cd rekonapp
composer install
npm install
```

### 2. Setup Environment File
Salin file `.env.example` menjadi `.env` dan atur konfigurasi database Anda:
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Jalankan Migrasi & Seeder
Jalankan migrasi database serta seed data master awal dan dummy data transaksi pengujian:
```bash
php artisan migrate --seed
```

### 4. Konfigurasi Aset & Jalankan Server Lokal
Kompilasi aset frontend menggunakan Vite dan jalankan server pengembangan:
```bash
npm run build
# atau untuk development:
npm run dev

php artisan serve
```

---

## 🧪 Pengujian (Testing)

Proyek ini dilengkapi dengan cakupan pengujian unit dan fitur yang komprehensif menggunakan **Pest PHP**. Untuk menjalankan tes:

```bash
./vendor/bin/pest
```

Pengujian mencakup otentikasi, otorisasi kebijakan role (Supervisor, Operator, Admin), pencetakan PDF, penyaringan filter laporan, impor CSV, otomatisasi klasifikasi, serta fitur Pindah Buku.

---

## 📄 Lisensi

RekonApp adalah perangkat lunak open-source di bawah lisensi [MIT](LICENSE).
