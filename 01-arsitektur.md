# Arsitektur Sistem

## Multi-tenant satu database

Setiap koperasi desa adalah satu tenant dengan pembukuan yang berdiri sendiri. Pemisahannya dilakukan lewat kolom `id_koperasi`, bukan database terpisah.

### Alasan memilih satu database

Konsinyasi antar desa melahirkan jurnal di dua pembukuan sekaligus, dan keduanya harus selalu cermin: Piutang Konsinyasi di Desa A wajib sama dengan Hutang Konsinyasi di Desa B. Dengan satu database, kedua jurnal itu dibuat dalam satu `DB::transaction()` — kalau salah satu gagal, keduanya rollback.

Dengan database terpisah per desa, konsistensi itu harus dijamin lewat mesin sinkronisasi antar database. Setiap kegagalan jaringan meninggalkan piutang gantung yang tidak punya lawan, dan tidak ada mekanisme otomatis yang bisa mendeteksinya.

### Risiko yang harus diterima

Satu database berarti satu titik kegagalan untuk semua desa, dan satu query yang lupa memfilter `id_koperasi` membocorkan data antar desa.

Mitigasi yang tidak opsional:

1. Global scope di semua model bertenant (`KoperasiScope`), bukan filter manual per query.
2. `id_koperasi` sebagai kolom pertama di setiap composite index.
3. Test otomatis yang memverifikasi tidak ada tabel bertenant yang bisa diakses lintas tenant.
4. Backup harian dengan point-in-time recovery.

### Cara kerja global scope

```php
// app/Models/Penjualan.php
class Penjualan extends Model
{
    use BelongsToKoperasi;   // memasang KoperasiScope otomatis
}
```

Setiap query `Penjualan::all()` otomatis jadi
`select * from penjualan where id_koperasi = <tenant aktif>`.

Tenant aktif diambil dari `auth()->user()->id_koperasi` lewat middleware `SetKoperasiAktif`. Pengguna tingkat pusat (`id_koperasi = null`) bisa memilih tenant lewat session, atau melihat konsolidasi dengan `Model::withoutGlobalScope(KoperasiScope::class)`.

**Jangan gunakan `withoutGlobalScope` di luar modul pelaporan konsolidasi.**

## Pembagian tanggung jawab

```
Controller      -> validasi request, panggil Service, kembalikan response
Service         -> SELURUH logika bisnis dan transaksi database
Model           -> relasi, cast, scope. Tanpa logika bisnis.
FormRequest     -> aturan validasi
```

Controller tidak boleh memanggil `DB::table()`, tidak boleh menulis jurnal, tidak boleh menghitung HPP.

## Alur data antar modul

```
        M0 MASTER & PERIODE  (fondasi)
                |
   +------------+-----------------+
   |                              |
M1 SURVEY                    M8 AKUNTANSI  (penerima akhir)
   |                              ^
M2 KALKULASI                      |
   |                              |
M3 PERENCANAAN                    |
   |                              |
   +--> M5 PEMBELIAN ----+        |
   |                     |        |
   +--> M6 PENJUALAN ----+--> M4 GUDANG --+
   |                     |                |
   +--> M7 KONSINYASI ---+                |
   |                                      |
   +--> M9 PIUTANG/HUTANG/KAS ------------+
                                          |
                                    M10 PELAPORAN
```

Empat aturan arah data:

1. Semua pergerakan barang lewat `StokService`. Modul lain tidak menyentuh tabel stok.
2. Semua transaksi berdampak keuangan menghasilkan jurnal lewat `JurnalService`.
3. M1–M3 adalah perencanaan: belum ada jurnal, belum ada stok.
4. Angka HPP hanya berasal dari `StokService`. Modul lain tidak menghitung ulang.

## State machine dokumen

Semua dokumen transaksional mengikuti pola yang sama:

```
DRAFT -> DIAJUKAN -> DISETUJUI -> DIPOSTING -> (DIBALIK)
              \-> DITOLAK           \-> DIBATALKAN
```

Hanya dokumen `DIPOSTING` yang menghasilkan jurnal dan mutasi stok. Dokumen yang sudah diposting tidak boleh diedit — koreksi dilakukan lewat jurnal pembalik.

## Idempotensi posting

`jurnal_header` punya unique key `(id_koperasi, source_type, source_id, jenis_jurnal)`. Menekan tombol posting dua kali menghasilkan `QueryException` pada unique violation, bukan jurnal ganda.

Hal yang sama berlaku di `kartu_stok` dengan unique key `(ref_tipe, ref_id, id_barang, jenis_mutasi)`.

Ini pengaman lapis database. Tetap pasang pengecekan `status_posting` di service — pengaman database adalah jaring terakhir, bukan yang pertama.

## Tipe data

| Jenis | Tipe | Alasan |
|---|---|---|
| Uang | `decimal(18,2)` | float membuat 0.1 + 0.2 ≠ 0.3, dan neraca tidak akan pernah balance |
| Kuantitas | `decimal(18,4)` | ikan 0,75 kg, beras 12,5 kg |
| Faktor konversi | `decimal(18,6)` | 1 ikat = 0,333333 kg |
| Persentase | `decimal(5,2)` | |

Di Laravel, pasang cast:

```php
protected $casts = [
    'total_bayar' => 'decimal:2',
    'qty_dasar'   => 'decimal:4',
];
```

Untuk perhitungan HPP moving average, gunakan `bcmath` atau `brick/math`, bukan aritmetika float PHP.
