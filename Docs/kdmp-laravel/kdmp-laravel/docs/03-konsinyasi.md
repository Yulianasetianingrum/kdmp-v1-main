# Model Akuntansi Konsinyasi Antar Desa

Dokumen ini menggantikan asumsi "penjualan kredit antar desa" dari revisi sebelumnya. Baca ini sebelum menyentuh modul penjualan, karena konsinyasi mengubah alur POS.

## Istilah

| Peran | Sebutan akuntansi | Kolom di sistem |
|---|---|---|
| Desa pemilik barang | Konsinyor / Pengamanat | `id_koperasi_pemilik` |
| Desa penjual titipan | Konsinyi / Komisioner | `id_koperasi_penerima` |

## Prinsip yang menentukan seluruh desain

**Barang titipan tetap milik Desa A sampai terjual ke warga.**

Empat konsekuensi langsung:

1. Saat pengiriman: tidak ada penjualan, tidak ada piutang, tidak ada hutang.
2. Barang titipan **tidak masuk Neraca Desa B**. Aset itu masih milik A.
3. Saat B menjual: omzet milik A, bukan B. B hanya mengakui imbalannya.
4. Persediaan A berkurang bukan saat kirim, tapi saat barang laku di B.

Melanggar salah satu dari empat poin ini membuat Neraca kedua desa salah, dan SHU yang dibagikan ke anggota dihitung dari angka fiktif.

## Dua model imbalan

Sistem mendukung keduanya lewat `pengiriman_konsinyasi.model_imbalan`.

| Model | Cara kerja | Kapan dipakai |
|---|---|---|
| `selisih_harga` | A menitipkan pada harga titip Rp 12.000, B bebas jual Rp 13.000, selisih milik B | Praktik paling umum di desa |
| `komisi_persen` | Harga jual ditentukan A, B dapat x% dari nilai penjualan | Kalau A ingin mengontrol harga eceran |

Contoh di bawah memakai `selisih_harga`.

---

## Lima peristiwa dan jurnalnya

Nilai contoh: HPP di A = Rp 8.000/kg, harga titip Rp 12.000/kg, harga jual ke warga Rp 13.000/kg. Dikirim 100 kg, laku 60 kg, sisa 40 kg dikembalikan.

### Peristiwa 1 — Desa A mengirim titipan (kode `KTK`)

**Buku Desa A** — reklasifikasi aset, bukan penjualan:

```
Persediaan Konsinyasi (1126)          D   800.000     (100 kg x 8.000)
    Persediaan Barang Jadi (1123)     K   800.000
```

**Buku Desa B — TIDAK ADA JURNAL.**

Hanya baris kuantitas di `stok_konsinyasi`. Ini titik yang paling sering salah: kalau Anda menjurnal penerimaan di B, Neraca B menggelembung dengan barang yang bukan miliknya, dan Neraca A kehilangan aset yang sebenarnya masih miliknya.

### Peristiwa 2 — Desa B menjual 60 kg ke warga (kode `KJL`)

Uang masuk = 60 × 13.000 = Rp 780.000
Hak Desa A = 60 × 12.000 = Rp 720.000
Imbalan Desa B = Rp 60.000

**Buku Desa B:**

```
Kas (1111)                            D   780.000
    Hutang Konsinyasi (2117)          K   720.000
    Pendapatan Imbalan Konsinyasi (417) K  60.000
```

Desa B **tidak** mencatat Pendapatan Penjualan dan **tidak** mencatat HPP. Omzet B dari transaksi ini Rp 60.000, bukan Rp 780.000.

### Peristiwa 3 — Desa A mengakui penjualan (kode `KAP`)

Dibuat otomatis pada `DB::transaction()` yang sama dengan peristiwa 2. Tidak perlu menunggu laporan manual dari Desa B, karena semua desa ada di satu database.

**Buku Desa A:**

```
Piutang Konsinyasi (1135)                    D   720.000
    Pendapatan Penjualan Konsinyasi (416)    K   720.000

HPP Penjualan Konsinyasi (514)               D   480.000    (60 kg x 8.000)
    Persediaan Konsinyasi (1126)             K   480.000
```

Laba kotor A = 720.000 − 480.000 = Rp 240.000.

> Pada model `komisi_persen`, harga jual ke warga sama dengan harga titip. A mencatat tambahan `Biaya Imbalan Konsinyasi (653) D / Piutang Konsinyasi (1135) K` sebesar komisi, dan B mencatat `Piutang Komisi` alih-alih menahan selisih.

### Peristiwa 4 — Desa B menyetor hasil ke Desa A (`KST` di B, `KTR` di A)

**Buku Desa B:**
```
Hutang Konsinyasi (2117)              D   720.000
    Kas / Bank                        K   720.000
```

**Buku Desa A:**
```
Kas / Bank                            D   720.000
    Piutang Konsinyasi (1135)         K   720.000
```

### Peristiwa 5 — Sisa 40 kg dikembalikan (kode `KRT`)

**Buku Desa A:**
```
Persediaan Barang Jadi (1123)         D   320.000     (40 kg x 8.000)
    Persediaan Konsinyasi (1126)      K   320.000
```

**Buku Desa B:** tidak ada jurnal. Kurangi `stok_konsinyasi`.

---

## Susut barang titipan — kebijakan yang harus Anda tetapkan

Beras menyusut, sayur busuk, ikan rusak. Ini akan terjadi. Kolom `penanggung_susut` mendukung dua opsi, tapi kebijakannya harus tertulis di perjanjian antar desa, bukan diputuskan operator per kasus.

| Opsi | Jurnal | Konsekuensi |
|---|---|---|
| `pemilik` — risiko di A | A: `Biaya Kerusakan (641) D / Persediaan Konsinyasi (1126) K` | B tidak menanggung apa pun, sehingga B tidak punya insentif menjaga barang |
| `penerima` — risiko di B | B: `Biaya Kerusakan (641) D / Hutang Konsinyasi (2117) K`; A mengakui seperti penjualan biasa | B punya insentif menjaga, tapi berpotensi jadi sengketa antar desa |

## Rekonsiliasi wajib sebelum tutup bulan

Tiga pemeriksaan yang harus lulus. Kalau gagal, ada modul yang menulis stok atau jurnal tanpa pasangannya.

```
1. SUM(stok_konsinyasi.qty_sisa x hpp_pengirim)
   =  saldo akun 1126 Persediaan Konsinyasi di buku desa pemilik

2. saldo 1135 Piutang Konsinyasi di buku Desa A (per mitra)
   =  saldo 2117 Hutang Konsinyasi di buku Desa B (per mitra)

3. qty_titip = qty_terjual + qty_retur + qty_susut + qty_sisa
```

Pemeriksaan nomor 2 hanya mungkin karena satu database. Kalau tiap desa punya database sendiri, selisih ini baru ketahuan saat audit tahunan — dan pada titik itu tidak ada lagi yang ingat transaksi mana yang bermasalah.

## Dampak ke modul Penjualan (POS)

Kasir Desa B melayani dua jenis barang di satu layar:

```
Barang milik sendiri  -> kode transaksi JTW / JTFW
                         Kas D / Pendapatan K / HPP D / Persediaan K

Barang titipan Desa A -> kode transaksi KJL
                         Kas D / Hutang Konsinyasi K / Pend. Imbalan K
                         + jurnal KAP otomatis di buku Desa A
```

Karena itu `detail_penjualan` punya kolom `id_stok_konsinyasi`. Kalau kolom itu terisi, baris tersebut diproses sebagai penjualan titipan. Satu nota bisa berisi campuran keduanya, dan mesin jurnal harus memecahnya jadi beberapa jurnal.

Batas waktu titipan disimpan di `tgl_batas_titip`. Tanpa batas waktu, barang bisa mengendap bertahun-tahun sebagai Persediaan Konsinyasi di buku A padahal wujudnya sudah tidak ada.
