# Panduan Impor CSV Transaksi & Rincian Transaksi Lengkap

Panduan ini menjelaskan cara menyiapkan dan mengimpor file CSV yang sudah berisi data mutasi rekening induk (**tabel `transaksi`**) beserta alokasi rincian akun objek penerimaannya (**tabel `transaksi_rincian`**) secara otomatis ke dalam database **RekonApp**.

File template contoh telah disediakan pada root proyek:
👉 [`template_import_transaksi_lengkap.csv`](file:///l:/proyek%20filamen/rekonapp/template_import_transaksi_lengkap.csv)

---

## 1. Struktur Header & Kolom CSV

File CSV harus memiliki baris judul kolom (*header*) sebagai berikut:

```csv
no_referensi,kode_bank,tanggal_transaksi,deskripsi,nominal,tipe_mutasi,kode_kanal,kode_instansi,status,kode_rekening,nominal_rincian
```

### Penjelasan Setiap Kolom:

| Nama Kolom | Wajib? | Tipe Data | Merujuk ke Tabel Master | Contoh Nilai | Deskripsi |
| :--- | :---: | :--- | :--- | :--- | :--- |
| **`no_referensi`** | Opsional | Teks | - | `TRX-20260610-001` | Pengelompok transaksi induk jika 1 pembayaran dipecah (*split*) ke beberapa rincian rekening. |
| **`kode_bank`** | **Wajib** | Teks | `relasi_bank.kode_bank` | `BANK_JABAR`, `BANK_BCA` | Kode bank aktif tempat transaksi terjadi. |
| **`tanggal_transaksi`** | **Wajib** | Tanggal | - | `2026-06-10` | Tanggal transaksi dengan format baku **`YYYY-MM-DD`**. |
| **`deskripsi`** | Opsional | Teks | - | `Penerimaan Pajak Hotel Melati` | Keterangan/memo mutasi bank. |
| **`nominal`** | **Wajib** | Angka | - | `15000000` | Nilai total mutasi induk (angka murni tanpa titik/koma ribuan). |
| **`tipe_mutasi`** | **Wajib** | Karakter | - | `D` atau `K` | `D` = Debet / Penerimaan Masuk, `K` = Kredit / Pengeluaran. |
| **`kode_kanal`** | Opsional | Teks | `kanal_pembayaran.kode` | `QRIS`, `ATM`, `TELLER`, `EDC`, `VA` | Kanal pembayaran ETPD (boleh dikosongkan jika manual/non-digital). |
| **`kode_instansi`** | Opsional | Teks | `instansi.kode_instansi` | `BAPENDA`, `DISHUB` | Kode instansi pengelola (opsional). |
| **`status`** | Opsional | Teks | - | `Posted` | Status transaksi: `Raw`, `Verified`, `Validated`, atau `Posted` (default: `Posted`). |
| **`kode_rekening`** | **Wajib** | Teks | `jenis_penerimaan.kode` | `4.1.01.01`, `4.1.02.02.001.00001` | Kode rekening jenis penerimaan yang ada di database. |
| **`nominal_rincian`** | Opsional | Angka | - | `15000000` | Nilai bagian rincian (jika kosong, otomatis mengisi senilai kolom `nominal`). |

---

## 2. Contoh Kasus Penyusunan Data

### Kasus 1: Satu Mutasi = Satu Rincian Rekening (1-to-1)
Setiap baris merupakan 1 transaksi utuh dan langsung dipetakan ke 1 kode rekening:

```csv
no_referensi,kode_bank,tanggal_transaksi,deskripsi,nominal,tipe_mutasi,kode_kanal,kode_instansi,status,kode_rekening,nominal_rincian
TRX-001,BANK_JABAR,2026-06-10,Penerimaan Pajak Hotel Bintang Lima,15000000,D,QRIS,BAPENDA,Posted,4.1.01.01,15000000
TRX-002,BANK_JABAR,2026-06-11,Penerimaan Pajak Restoran Padang,5000000,D,TELLER,BAPENDA,Posted,4.1.02.02.001.00001,5000000
TRX-003,BANK_BCA,2026-06-12,Penerimaan Pajak Reklame,2500000,D,ATM,BAPENDA,Posted,4.1.02.02.001,2500000
```

### Kasus 2: Satu Mutasi Dipecah ke Banyak Rincian Rekening (*Split Penerimaan*)
Gunakan nilai **`no_referensi`** yang sama pada baris-baris rincian yang bersangkutan:

```csv
no_referensi,kode_bank,tanggal_transaksi,deskripsi,nominal,tipe_mutasi,kode_kanal,kode_instansi,status,kode_rekening,nominal_rincian
TRX-GABUNGAN-01,BANK_BCA,2026-06-13,Pembayaran Gabungan Hotel dan Restoran,25000000,D,TELLER,BAPENDA,Posted,4.1.01.01,15000000
TRX-GABUNGAN-01,BANK_BCA,2026-06-13,Pembayaran Gabungan Hotel dan Restoran,25000000,D,TELLER,BAPENDA,Posted,4.1.02.02.001,10000000
```
> **Hasil di Database:**
> Sistem hanya membuat **1 data transaksi induk** senilai Rp 25.000.000, dan membuat **2 baris rincian**:
> 1. Akun `4.1.01.01` senilai Rp 15.000.000
> 2. Akun `4.1.02.02.001` senilai Rp 10.000.000

---

## 3. Cara Menjalankan Impor Data

Telah disediakan perintah Artisan bawaan untuk menjalankan proses impor secara instan:

### Perintah Standar (Pemisah Koma `,`)
```powershell
php artisan import:transaksi-lengkap template_import_transaksi_lengkap.csv
```

### Jika File CSV Menggunakan Pemisah Titik Koma (`;`)
*(Umum terjadi jika file disimpan dari Microsoft Excel berbahasa Indonesia)*
```powershell
php artisan import:transaksi-lengkap path/ke/file_anda.csv --delimiter=";"
```

---

## 4. Tips Penting Saat Membuat File di Microsoft Excel / Google Sheets

1. **Format Tanggal**:
   - Pastikan tanggal berformat `YYYY-MM-DD` (contoh: `2026-06-10`).
   - Di Excel, Anda dapat mengubah format cell melalui: *Format Cells $\rightarrow$ Custom $\rightarrow$ Ketik:* `yyyy-mm-dd`.
2. **Format Angka / Nominal**:
   - Jangan menggunakan pemisah ribuan berupa titik atau simbol mata uang (contoh yang benar: `15000000`, bukan `Rp 15.000.000`).
3. **Format Penyimpanan CSV**:
   - Di Microsoft Excel: Pilih **File $\rightarrow$ Save As $\rightarrow$ CSV UTF-8 (Comma delimited) (*.csv)**.

---

## 5. Mekanisme Keamanan Database

- **Database Transaction (`DB::transaction`)**:
  Proses impor dilindungi oleh transaksi database. Jika terjadi kesalahan fatal di tengah jalan, seluruh proses dibatalkan (*rollback*) sehingga database tidak terisi data setengah jadi.
- **Pencocokan Otomatis Periode Pembukuan**:
  Sistem secara otomatis menghubungkan transaksi ke `periode_pembukuan_id` yang cocok berdasarkan bank, bulan, dan tahun transaksi.
