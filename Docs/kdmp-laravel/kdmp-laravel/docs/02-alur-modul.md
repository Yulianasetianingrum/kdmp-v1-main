# Alur Per Modul

Setiap bagian menyebutkan: pemicu, langkah, jurnal yang dihasilkan, dan service yang bertanggung jawab.

---

## M0 — Master & Periode

| Fitur | Tabel | Catatan |
|---|---|---|
| Koperasi desa | `koperasi_desa` | satu baris per desa; ini tenant |
| Periode akuntansi | `periode_akuntansi` | bulan 1–12 operasional, bulan 13 penyesuaian |
| COA | `master_coa` | global, dipakai semua desa; saldonya yang bertenant |
| Kas & bank | `master_kas_bank` | per desa; inilah yang membuat transaksi transfer bisa dijurnal |
| Gudang | `gudang` | satu per desa |
| Barang, satuan, konversi | `master_barang`, `satuan`, `konversi_satuan` | stok selalu disimpan dalam satuan dasar |
| Pihak | `master_pihak` | flag `is_anggota` menentukan akun pendapatan dan hak SHU |

**Aturan periode.** Tahun N bulan 13 boleh tetap `OPEN` sampai Maret tahun N+1, sementara tahun N+1 bulan 1–3 sudah berjalan. Periode ditentukan dari `tanggal_jurnal`, bukan tanggal input.

---

## M1 — Survey

```
Admin buat sesi survei -> sistem terbitkan token URL + kedaluwarsa
  -> URL dibagikan ke pengurus desa
  -> pengurus isi via suara (STT) atau manual
  -> status: draft -> terkirim
  -> admin verifikasi -> disetujui
  -> hanya data 'disetujui' yang masuk M2
```

Tiga jenis data yang dikumpulkan, jangan dicampur:

1. **Demografi** → `demografi_desa` (jumlah penduduk per kelompok umur)
2. **Potensi produksi** → `ketersediaan_komoditas` (per bulan panen, bukan angka tahunan dibagi 12)
3. **Harga pasar** → dipakai `perbandingan_harga`

Tidak ada jurnal di modul ini.

---

## M2 — Hitung Kalkulasi

```
Kebutuhan(produk, desa, bulan) =
    SUM( jumlah_penduduk(desa, kelompok_umur)
         x per_kapita_harian(kelompok_umur, produk) x hari_dalam_bulan )
    x faktor_musiman
```

Hasil disimpan sebagai **snapshot** ke `kebutuhan_komoditas`, bukan dihitung on-the-fly. Kalau dihitung ulang setiap dibuka, revisi koefisien akan mengubah angka rencana bulan lalu secara retroaktif dan menghapus jejak dasar keputusan pembelian.

Kolom `id_standar` menyimpan versi koefisien yang dipakai.

Tidak ada jurnal.

---

## M3 — Perencanaan

```
Neraca Komoditas = Produksi (M1) - Kebutuhan (M2)   -> hasil_kalkulasi

Kebutuhan Pengadaan Bersih
   = Kebutuhan (M2)
   - Stok On Hand (M4)
   - On Order (PO belum diterima, M5)
   + Safety Stock
```

Output: draft `permintaan_pengadaan` (PR) dengan detail multi-barang.

State: `draft -> diajukan -> disetujui -> jadi_pembelian`

Tidak ada jurnal.

---

## M4 — Gudang (Moving Average)

Service: `StokService`

### Rumus

```
HPP_baru = (nilai_persediaan_lama + nilai_masuk) / (qty_lama + qty_masuk)
```

Setiap penerimaan memperbarui `stok.hpp_rata2`. Setiap pengeluaran memakai `hpp_rata2` yang berlaku saat itu dan **tidak** mengubahnya.

### Penerimaan barang

```
Barang datang -> input GRN (multi-item)
  -> sortir per baris: qty_layak / qty_tidak_layak
  -> qty_layak masuk stok, hpp_rata2 dihitung ulang
  -> kartu_stok jenis IN
  -> jurnal: Persediaan (D) / Kas atau Hutang Dagang (K)
  -> qty_tidak_layak masuk antrean retur pembelian
```

### Pengeluaran barang

```
Penjualan dikonfirmasi
  -> kartu_stok jenis OUT senilai hpp_rata2
  -> jurnal: HPP (D) / Persediaan (K)
```

### Stock opname

```
Buat sesi -> freeze barang yang dihitung -> input fisik
  -> sistem hitung selisih -> approval Manajer -> posting
  Fisik < sistem: Biaya Kerusakan (641) D / Persediaan K
  Fisik > sistem: Persediaan D / Selisih Lebih Stok (712) K
```

### Larangan

Mutasi mundur tanggal dilarang. Koreksi dilakukan dengan mutasi baru bertanggal hari ini. Kalau backdate diizinkan, seluruh `saldo_nilai` dan `hpp_rata2_setelah` di bawahnya menjadi salah tanpa mekanisme deteksi.

---

## M5 — Pembelian

Alur dua langkah sesuai keputusan Anda:

```
PR disetujui -> buat Pembelian -> barang datang -> GRN -> posting
Jurnal: Persediaan (D) / Kas, Bank, atau Hutang Dagang (K)
```

### Pembelian dari petani (jalur cepat)

Petani datang, ditimbang, langsung bayar tunai. Tanpa PR, tanpa PO.

```
Nota Pembelian Petani -> GRN + pembayaran sekaligus
Jurnal (BPT): Persediaan (D) / Kas (K)
```

Sediakan jalur ini. Kalau petugas lapangan dipaksa lewat alur PO penuh, mereka akan bypass sistem dan data Anda jadi bohong.

### Retur pembelian

| Penyelesaian | Jurnal |
|---|---|
| Diganti uang (`RBU`) | Kas (D) / Persediaan (K) |
| Potong hutang (`RBH`) | Hutang Dagang (D) / Persediaan (K) |
| Ganti barang | tanpa jurnal nilai, hanya swap kartu stok |

---

## M6 — Penjualan

Ke warga: **selalu tunai atau transfer**. Kode `JKW` sengaja tidak ada.

```
Kasir pindai barang -> cek qty_available -> hitung total
  -> pilih metode bayar (kas / bank)
  -> posting
Jurnal (JTW): Kas (D) / Pendapatan (K) / HPP (D) / Persediaan (K)
```

Akun pendapatan dipilih berdasarkan `is_pembeli_anggota` dan unit usaha:

| Anggota | Unit | Akun |
|---|---|---|
| Ya | Sembako | 411 |
| Tidak | Sembako | 412 |
| Ya | Apotek | 413 |
| Tidak | Apotek | 414 |

`is_pembeli_anggota` **disalin** ke tabel penjualan saat transaksi, tidak di-join saat pelaporan. Kalau di-join, warga yang keluar dari keanggotaan tahun depan akan mengubah angka SHU tahun-tahun sebelumnya.

### Retur penjualan

Gunakan akun kontra `421 Retur Penjualan`, jangan mendebit akun Pendapatan langsung. Mendebit Pendapatan menghapus jejak berapa retur yang terjadi.

```
Retur Penjualan (421)     D
    Kas atau Piutang      K
Persediaan                D
    HPP                   K
```

---

## M7 — Konsinyasi Antar Desa

Alur lengkap dan jurnalnya ada di `03-konsinyasi.md`. Ringkasan alur operasional:

```
Desa A: buat Pengiriman Konsinyasi -> pilih barang, qty, harga titip,
        model imbalan, penanggung susut, batas waktu
     -> posting (KTK): Persediaan Konsinyasi D / Persediaan K
     -> kirim

Desa B: terima -> verifikasi qty -> masuk stok_konsinyasi
     -> TIDAK ADA JURNAL

Desa B: kasir jual barang titipan
     -> posting (KJL) di buku B: Kas D / Hutang Konsinyasi K / Imbalan K
     -> posting (KAP) di buku A OTOMATIS, transaksi yang sama:
        Piutang Konsinyasi D / Pendapatan Konsinyasi K
        HPP Konsinyasi D / Persediaan Konsinyasi K

Desa B: setor hasil (KST) -> Desa A terima (KTR)

Desa A: minta sisa dikembalikan (KRT)
     -> Persediaan D / Persediaan Konsinyasi K
```

Marketplace pencocokan surplus-defisit (`permintaan_barter`, `penawaran_barter`) tetap dipertahankan sebagai lapisan pencarian mitra. Setelah cocok, dokumen yang dibuat adalah `pengiriman_konsinyasi`.

---

## M9 — Piutang, Hutang, Kas

Tiga buku pembantu yang harus selalu cocok dengan Buku Besar:

```
SUM(piutang.sisa)  = saldo akun 1132 + 1133 + 1135
SUM(hutang.sisa)   = saldo akun 2111 + 2117
SUM(kas_bank)      = saldo akun 1111 + 1112x
```

Ketidakcocokan berarti ada modul yang menulis salah satunya tanpa yang lain. Ini diperiksa otomatis saat tutup bulan.

Transaksi yang tersedia: pelunasan piutang (`TPI`), pembayaran hutang (`BHU`), offset (`OFS`), kas masuk/keluar lain (`KSM`/`KSK`), simpanan anggota (`SPK`/`SWJ`/`SSK`/`TSK`).

---

## M8 — Akuntansi

### Mesin jurnal

```php
JurnalService::posting(
    kodeTransaksi: 'JTW',
    koperasiId: 1,
    tanggal: '2026-07-28',
    payload: [
        'total_bayar' => 130000,
        'total_hpp'   => 100000,
        'id_kas_bank' => 3,
        'id_unit_usaha' => 1,
        'is_anggota'  => false,
    ],
    sourceType: 'penjualan',
    sourceId: 501,
);
```

Service membaca `master_detail_transaksi`, menerjemahkan akun dinamis (`KAS_BANK`, `PERSEDIAAN_UNIT`, `PENDAPATAN_UNIT`, `HPP_UNIT`) menjadi akun konkret, lalu memvalidasi Debet = Kredit sebelum menyimpan.

### Jurnal pembalik

Jurnal yang sudah POSTED tidak boleh diedit atau dihapus. Koreksi dilakukan lewat `JurnalService::balik()`, yang menyalin detail dengan D dan K ditukar, lalu menandai jurnal asal `REVERSED`.

Kalau jurnal asal berdampak stok, kartu stok juga harus dibalik. Membalik jurnal tanpa membalik kartu stok membuat nilai Persediaan di Neraca berbeda dari nilai di Gudang.

### Tutup bulan

Delapan validasi yang harus lulus sebelum periode boleh ditutup:

```
1. Tidak ada dokumen berstatus draft
2. Semua jurnal periode ini berstatus POSTED
3. Total Debet = Total Kredit
4. Nilai persediaan di Buku Besar = SUM(stok.nilai_persediaan)
5. Total piutang di Buku Besar = SUM(piutang.sisa)
6. Total hutang di Buku Besar = SUM(hutang.sisa)
7. Persediaan Konsinyasi di BB = SUM(stok_konsinyasi sisa x hpp)
8. Piutang Konsinyasi A = Hutang Konsinyasi B (per pasangan desa)
```

Validasi 4–8 adalah pengaman terpenting. Kalau salah satu gagal, ada modul yang menulis stok atau piutang tanpa menjurnal.

### Tutup tahun

```
1. Semua bulan 1-13 sudah CLOSED
2. Jurnal penutup: Pendapatan (4) dan Non-Op (7) -> Ikhtisar (811)
                   Ikhtisar (811) -> HPP (5) dan Biaya (6)
                   Ikhtisar (811) -> SHU Tahun Berjalan (341)
3. Pembagian SHU sesuai config_shu:
   SHU Tahun Berjalan (341) D
       Cadangan Umum (331) K
       Dana Pendidikan (332) K
       Dana Sosial (333) K
       Dana Pengurus (334) K
       Dana Pembagian SHU Belum Dibayar (2116) K   <- jasa anggota
4. Akun 4,5,6,7 bersaldo nol
5. Akun 1,2,3 dibawa ke tahun berikutnya
6. Set tahun LOCKED
```

Jasa anggota dihitung dari nilai transaksi tiap anggota sepanjang tahun (akun 411 dan 413), bukan dibagi rata. Inilah sebabnya `is_anggota` harus tersimpan di setiap nota penjualan.

---

## M10 — Pelaporan

Neraca dan Laba Rugi diambil dari view `v_saldo_berjalan` untuk bulan berjalan, dan dari `buku_besar_periode` untuk bulan yang sudah ditutup. Keduanya memfilter berdasarkan `master_coa.kelompok`:

| Laporan | Kelompok akun |
|---|---|
| Neraca | Aktiva, Kewajiban, Modal |
| Laba Rugi | Pendapatan, HPP, Biaya, Non-Operasional |
| Neraca Saldo | semua akun `is_transaction = T` |
| Arus Kas | mutasi akun 1111, 1112x |

Akun dengan `is_kontra = 1` dikurangkan, bukan ditambahkan.
