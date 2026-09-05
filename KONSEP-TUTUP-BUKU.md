# 📚 KONSEP & REALITA TUTUP BUKU (YEAR-END CLOSING) DALAM ERP

> **Dokumen Landasan Desain ERP Keuangan KDMP**
> *Ditulis untuk menjawab tantangan akademis terkait "Bagaimana kasir jualan di tahun baru, sementara akuntan masih mengotak-atik data tahun lalu?"*

---

## 1. Mitos vs Realita Tutup Buku

Di atas kertas (teori kampus), tutup buku (Closing) dikunci tepat pada tanggal **31 Desember pukul 23:59**. Pada **1 Januari pukul 00:01**, semua akun nominal (Pendapatan & Biaya) bernilai NOL, dan periode baru dimulai.

**Di dunia nyata, hal tersebut MUSTAHIL.** 
- Toko/Koperasi tetap beroperasi di tanggal 1 Januari.
- Akuntan butuh *Grace Period* (Masa Tenggang) berminggu-minggu hingga Maret untuk menghitung penyusutan 12 bulan, stock opname akhir tahun, rekonsiliasi bank, dan menunggu hasil audit.

Oleh karena itu, ERP Koperasi KDMP menggunakan sistem **Filter Periode (Dua Garis Waktu)**.

---

## 2. Pemisahan "Tanggal Input (Realtime)" dan "Tanggal Transaksi"

Di dalam database KDMP, pembeda utamanya BUKAN kapan data itu diketik (`created_at`), melainkan untuk periode kapan transaksi itu diakui (`tanggal_transaksi` / `periode_tahun` & `periode_bulan`).

### Analogi "Dua Ember Laba"

Bayangkan ada dua ember tak kasat mata di dalam database:

#### 🪣 EMBER 2026 (Milik Akuntan)
Berisi semua jurnal transaksi dengan `periode_tahun = 2026`. 
Di bulan Januari, Februari, hingga Maret 2027 (Waktu Nyata), Akuntan Koperasi masih terus membuka form Jurnal Penyesuaian, dan men-setting **tanggal transaksi mundur (backdate) ke 31 Desember 2026**. Data ini aman masuk ke Ember 2026 untuk mengoreksi Laba/Rugi tahun lalu.

#### 🪣 EMBER 2027 (Milik Kasir)
Pada 1 Januari 2027, Kasir melayani pembeli. Modul Penjualan menembak `JurnalService` dengan `tanggal_transaksi = 2027-01-01`. 
Uang masuk dan HPP otomatis menumpuk di Ember 2027. Omzet ini terhitung mulai dari NOL untuk tahun yang baru.

---

## 3. Eksekusi "Hard Close" (Jurnal Penutup)

Tombol "Tutup Buku" bukanlah saklar listrik yang mematikan aplikasi.
Ketika di akhir Maret 2027, Akuntan menekan tombol **"Finalisasi Tutup Tahun 2026"**, sistem akan memicu sebuah `Stored Procedure`:

1. **Filter Super Ketat:** Sistem HANYA mengambil total Pendapatan dan Biaya dari Ember 2026 (berdasarkan `periode_tahun = 2026`). *Ember 2027 milik kasir sama sekali tidak tersentuh.*
2. **Pembuatan Jurnal Penutup (Closing Entries):**
   Sistem membuat Jurnal otomatis tertanggal `2026-12-31`:
   - `[D]` Semua Akun Pendapatan (Untuk meng-NOL-kan saldonya)
   - `[K]` Semua Akun Biaya & HPP (Untuk meng-NOL-kan saldonya)
   - `[K]` Akun Modal / SHU Ditahan (Keuntungannya dipindah menjadi Modal).
3. **Penguncian:** Status `periode_akuntansi` tahun 2026 diubah menjadi `CLOSED` (Tutup). Jika Akuntan mencoba backdate lagi ke 2026, `PeriodeTutupException` akan dilempar menolak transaksi.

---

## 💬 Jawaban Pamungkas untuk Dosen Anda

Jika Dosen Anda menguji logika sistem Anda, gunakan argumen ini:

> *"Pak/Bu, di sistem ERP Koperasi kami, Tutup Buku bukan berarti mematikan sistem kasir. Kami membedakan 'Tahun Buku' dan 'Waktu Real-time' menggunakan tanggal transaksi. Kasir tetap bisa jualan di tahun berjalan (omzet otomatis mulai dari nol). Sementara itu, Akuntan diberikan 'Grace Period' hingga bulan Maret untuk memasukkan jurnal penyesuaian dengan backdate (tanggal mundur) ke 31 Desember tahun sebelumnya. Nanti saat Akuntan menekan tombol Finalisasi, sistem hanya akan menarik Laba/Rugi dari tahun yang difinalisasi untuk di-NOL-kan menggunakan Jurnal Penutup otomatis, lalu labanya dipindah permanen ke akun Modal (SHU). Omzet hasil kerja keras kasir di bulan Januari hingga Maret tahun berjalan dijamin 100% aman dan tidak ikut ter-Nol-kan."*
