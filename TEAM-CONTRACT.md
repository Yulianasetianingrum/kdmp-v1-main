# 🤝 KONTRAK KERJA TIM — Integrasi Antar Modul

> **DOKUMEN PENTING UNTUK DEVELOPER**
> Ini adalah dokumen resmi pembagian kerja tim KDMP dan kesepakatan pengiriman data (payload) ke Modul Akuntansi (Finance). Sistem menggunakan pola **Single Entry, Multiple Impact**, di mana penulisan data uang/jurnal difokuskan pada satu titik.

---

## 👥 PEMBAGIAN TUGAS TIM

*   **Ketua**     : `feature/finance` (Keuangan, Akuntansi, Master Akun, Jurnal)
*   **Anggota 2** : `feature/survei` (Data Sesi Survei, Pertanyaan, Demografi)
*   **Anggota 3** : `feature/perencanaan` (Analisis Kebutuhan, RAB Pengadaan)
*   **Anggota 4** : `feature/gudang-konsinyasi` (Master Barang, Kartu Stok, Opname, Titip Barang)
*   **Anggota 5** : `feature/pembelian-penjualan` (Kulakan/Beli, Kasir/Jual, Retur)

---

## 🚫 ATURAN UTAMA
Hanya **Ketua (Tim Finance)** yang diizinkan melakukan `INSERT/UPDATE/DELETE` ke tabel:
- `jurnal_header`, `jurnal_detail`
- `buku_besar_periode`

Anggota 4 dan Anggota 5 yang membutuhkan pencatatan uang/akuntansi **WAJIB** memanggil `JurnalService->posting()` dari baris terakhir Controller mereka masing-masing.

---

## 📡 DETAIL KONTRAK API PER ANGGOTA

### 🧑‍💻 ANGGOTA 2 (Survei)
Fokus Utama: Mengumpulkan data lapangan (Sesi Survei, Pertanyaan, Data Demografi).
**Interaksi dengan Finance:** Secara teknis, **TIDAK ADA**. Modul ini menyuplai data mentah untuk dikalkulasi oleh Anggota 3.

---

### 📊 ANGGOTA 3 (Perencanaan)
Fokus Utama: Mengolah data dari Survei & Gudang menjadi **Keputusan Pengadaan (Beli/Barter/Retur)**.

**Rincian Tugas Anggota 3:**
1. **Hitung Kalkulasi Konsumsi Penduduk:** 
   Menggunakan data survei demografi (jumlah anak balita, remaja, dewasa, lansia) untuk memprediksi kebutuhan komoditas pangan. Hasilnya direkap per produk dan per desa per bulan.
2. **Manajemen Kebutuhan & Kelebihan:**
   - Menerima rekap hitungan kebutuhan vs ketersediaan.
   - Menerima informasi dari Anggota 4 (Gudang) tentang status stok: apakah ada barang kurang, kelebihan (nunggu pengiriman), proses sortir, atau butuh diretur.
3. **Manajemen Barter Komoditas Antar Desa:**
   - Menganalisis kecocokan antara desa yang surplus (kelebihan) dengan desa yang defisit (kekurangan) suatu komoditas.
   - Menginisiasi penawaran dan permintaan tukar-menukar barang (barter) antar desa agar stok merata tanpa harus mengeluarkan uang tunai.
4. **Perbandingan Harga & Aksi Pengadaan:**
   - Membandingkan harga termurah dari desa-desa lain.
   - Mencetak keputusan: Apakah harus Kulakan (meminta Anggota 5 melakukan Pembelian), Barter (meminta Anggota 4 Konsinyasi), atau menyerap hasil panen Petani lokal.

**Interaksi dengan Finance:** 
- **TIDAK ADA jurnal uang yang langsung tercipta di modul ini.** 
- Modul ini adalah *Otak Pengambil Keputusan*. Jurnal uang baru akan tercatat ketika Anggota 5 (Pembelian) benar-benar membelanjakan uang untuk pengadaan tersebut, atau Anggota 4 merealisasikan retur.

### 📦 ANGGOTA 4 (Gudang & Konsinyasi)
Fokus kalian adalah mutasi *Fisik Barang* dan perpindahan lokasi konsinyasi.
**Interaksi dengan Finance:**

**1. Saat Barang Rusak / Hilang (Gudang)**
Jika ada fisik barang yang hilang/rusak, kalian harus lapor nilai kerugiannya (HPP) ke Akuntansi.
```php
$payloadKeuangan = [
    'tanggal_jurnal' => now()->toDateString(),
    'kode_unit'      => 'TKO',      // Unit tempat barang berada
    'total_hpp'      => 50000,      // Nilai rupiah barang yang rusak/hilang (Modal)
];
$jurnalService->posting('BKR', $payloadKeuangan, KerusakanBarang::class, $id);
```

**2. Saat Konsinyasi Laku & Disetor**
Kalian mencatat komisi penerima dan HPP asli pengirim.
```php
$payloadKeuangan = [
    'tanggal_jurnal' => now()->toDateString(),
    'kode_unit'      => 'TKO',
    'id_kas_bank'    => 1,
    'total_setoran'  => 130000,      // Uang bersih yang diterima
    'total_komisi'   => 20000,       // Komisi yang dipotong penerima
    'total_hpp'      => 100000,      // HPP asli barang
];
$jurnalService->posting('KST', $payloadKeuangan, SetoranKonsinyasi::class, $id);
```

---

### 🛒 ANGGOTA 5 (Pembelian & Penjualan)
Kalian adalah mesin uang koperasi. Tugas kalian menghitung total belanja, total bayar kasir, dan yang paling penting: **Menghitung Total HPP (Harga Pokok Penjualan)** dari barang yang laku.

**Interaksi dengan Finance:**

**1. Saat Kasir Menjual Barang (Penjualan)**
```php
$payloadKeuangan = [
    'tanggal_jurnal' => now()->toDateString(),
    'nomor_nota'     => 'INV-123',
    'kode_unit'      => 'TKO',
    'id_kas_bank'    => 1,           // Jika Tunai, masuk laci kas mana?
    'total_bayar'    => 150000,      // Uang masuk kas (Rp)
    'total_hpp'      => 100000,      // Total Harga Modal dari barang yang laku (Rp)
    'is_anggota'     => true,        // Penentu Akun Pendapatan (Anggota/Bukan)
    'id_pihak'       => 5,           // WAJIB jika pembeli Ngutang (Piutang)
];
$jurnalService->posting('JTW', $payloadKeuangan, Penjualan::class, $id); // JTW = Tunai
```

**2. Saat Kulakan Barang (Pembelian)**
```php
$payloadKeuangan = [
    'tanggal_jurnal' => now()->toDateString(),
    'nomor_nota'     => 'BELI-001',
    'kode_unit'      => 'TKO',
    'id_kas_bank'    => 1,           // Jika bayar tunai
    'total_beli'     => 1500000,     // Total nilai uang belanja (Rp)
    'id_pihak'       => 10,          // WAJIB diisi dengan ID Supplier
];
$jurnalService->posting('BLY', $payloadKeuangan, Pembelian::class, $id); // BLY = Beli Tunai
```

---

### 🏦 KETUA (Finance)
Fokus pada kasbon, beban operasional, bayar hutang, pelunasan piutang, dan tutup bulan.
Menggunakan `JurnalService->postingManual()` untuk transaksi bebas atau `posting('PLN')` untuk pelunasan hutang/piutang yang memiliki template. 
Segala *bug* yang muncul dengan label "Jurnal Tidak Balance" atau "Akun Dinamis Tidak Ditemukan" adalah tanggung jawab Ketua untuk menyelesaikannya.
