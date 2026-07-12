# Tambahan PRD - Laporan Hierarki Konsolidasi Multi-Tenant

## Pengaturan Sebelum Cetak

Sebelum proses cetak, sistem menampilkan form konfigurasi laporan.

### Informasi Instansi

* Nama Instansi
* Alamat Instansi
* Judul Laporan
* Sub Judul (opsional)
* Periode Laporan
* Tanggal Cetak (otomatis, dapat diubah)
* Penandatangan (opsional)

Informasi ini hanya digunakan sebagai header laporan dan tidak mengubah data master aplikasi.

---

## Struktur Laporan

Laporan menggunakan struktur **Kode Penerimaan** yang bersifat hierarki.

Contoh:

```
4      Pendapatan Daerah
4.1    Pajak Daerah
4.1.1  Pajak Reklame
4.1.2  Pajak Restoran
4.1.3  Pajak Air Tanah
4.2    Retribusi Daerah
```

Urutan laporan mengikuti struktur kode penerimaan dari level tertinggi hingga level terendah.

---

## Header Tabel

| Kode Penerimaan | Nama Penerimaan | Nama Tenant | Jumlah |

---

## Isi Laporan

Untuk setiap **Kode Penerimaan** ditampilkan seluruh tenant yang memiliki transaksi **Posted**.

Contoh:

| Kode  | Nama Penerimaan             | Nama Tenant  |            Jumlah |
| ----- | --------------------------- | ------------ | ----------------: |
| 4.1.1 | Pajak Reklame               | Bank Kalteng |     Rp150.000.000 |
|       |                             | Bank Mandiri |     Rp275.000.000 |
|       |                             | BRI          |      Rp95.000.000 |
|       | **Subtotal Pajak Reklame**  |              | **Rp520.000.000** |
| 4.1.2 | Pajak Restoran              | Bank Kalteng |      Rp80.000.000 |
|       |                             | Bank Mandiri |     Rp120.000.000 |
|       | **Subtotal Pajak Restoran** |              | **Rp200.000.000** |

---

## Ketentuan Data

* Hanya mengambil transaksi dengan status **Posted**.
* Tenant yang tidak memiliki transaksi pada kode penerimaan tersebut tidak ditampilkan.
* Data dikelompokkan berdasarkan:

  1. Kode Penerimaan
  2. Tenant (misalnya nama bank)

---

## Total

Pada setiap kode penerimaan ditampilkan subtotal.

Pada akhir laporan ditampilkan:

* Grand Total seluruh kode penerimaan.
* Grand Total seluruh tenant.

---

## Pengurutan

* Kode penerimaan diurutkan secara hierarki.
* Tenant diurutkan berdasarkan nama tenant.

---

## Format Output

Mendukung:

* PDF (A4 Portrait/Landscape sesuai kebutuhan)
* Excel

Format tabel harus sama pada PDF maupun Excel.

Dengan struktur ini, laporan akan menyerupai laporan konsolidasi keuangan: setiap **kode penerimaan** menjadi kelompok utama, sedangkan di bawahnya ditampilkan rincian **tenant (misalnya nama bank)** beserta jumlah transaksi, diakhiri dengan subtotal per kode dan grand total keseluruhan.
