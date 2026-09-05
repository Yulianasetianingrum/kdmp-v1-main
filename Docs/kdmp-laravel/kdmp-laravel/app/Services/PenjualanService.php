<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penjualan ke warga. SELALU tunai atau transfer — kode JKW tidak ada.
 *
 * Satu nota boleh memuat campuran:
 *   - barang milik sendiri  -> JTW / JTFW
 *   - barang titipan desa lain -> KJL (+ KAP otomatis di buku pemilik)
 *
 * Baris dibedakan dari kolom detail_penjualan.id_stok_konsinyasi.
 */
class PenjualanService
{
    public function __construct(
        private JurnalService $jurnal,
        private StokService $stok,
        private KonsinyasiService $konsinyasi,
    ) {}

    public function posting(int $idPenjualan): void
    {
        DB::transaction(function () use ($idPenjualan) {
            $jual = DB::table('penjualan')->where('id_penjualan', $idPenjualan)->lockForUpdate()->first();

            if (! $jual) {
                throw new RuntimeException("Penjualan {$idPenjualan} tidak ditemukan.");
            }
            if ($jual->status_posting === 'T') {
                throw new RuntimeException('Penjualan ini sudah diposting.');
            }

            $detail = DB::table('detail_penjualan')->where('id_penjualan', $idPenjualan)->get();

            $barisSendiri   = $detail->whereNull('id_stok_konsinyasi');
            $barisKonsinyasi = $detail->whereNotNull('id_stok_konsinyasi');

            // ---- 1. Baris barang milik sendiri ----
            $totalBayarSendiri = '0';
            $totalHpp          = '0';

            foreach ($barisSendiri as $d) {
                $nilaiHpp = $this->stok->keluar(
                    koperasiId: $jual->id_koperasi,
                    gudangId:   $jual->id_gudang,
                    barangId:   $d->id_barang,
                    qty:        (string) $d->qty_dasar,
                    tanggal:    $jual->tanggal_transaksi,
                    refTipe:    'PENJUALAN',
                    refId:      $idPenjualan,
                );

                DB::table('detail_penjualan')->where('id_detail', $d->id_detail)->update([
                    'hpp_satuan_dasar' => bcdiv($nilaiHpp, (string) $d->qty_dasar, 4),
                    'total_hpp'        => $nilaiHpp,
                ]);

                $totalHpp          = bcadd($totalHpp, $nilaiHpp, 2);
                $totalBayarSendiri = bcadd($totalBayarSendiri, (string) $d->subtotal, 2);
            }

            $idJurnalUtama = null;

            if (bccomp($totalBayarSendiri, '0', 2) > 0) {
                $idJurnalUtama = $this->jurnal->posting(
                    kodeTransaksi: $jual->metode_bayar === 'tunai' ? 'JTW' : 'JTFW',
                    koperasiId:    $jual->id_koperasi,
                    tanggal:       $jual->tanggal_transaksi,
                    payload:       [
                        'total_bayar'   => $totalBayarSendiri,
                        'total_hpp'     => $totalHpp,
                        'id_kas_bank'   => $jual->id_kas_bank,
                        'id_unit_usaha' => $jual->id_unit_usaha,
                        'is_anggota'    => (bool) $jual->is_pembeli_anggota,
                        'id_pihak'      => $jual->id_pihak,
                    ],
                    sourceType: 'penjualan',
                    sourceId:   $idPenjualan,
                    keterangan: "Penjualan {$jual->kode_penjualan}",
                );
            }

            // ---- 2. Baris barang titipan ----
            // Setiap baris melahirkan jurnal di DUA pembukuan.
            foreach ($barisKonsinyasi as $d) {
                $this->konsinyasi->jualTitipan(
                    idStokKonsinyasi: (int) $d->id_stok_konsinyasi,
                    qty:              (string) $d->qty_dasar,
                    hargaJualSatuan:  (string) $d->harga_satuan,
                    idPenjualan:      $idPenjualan,
                    idKasBank:        (int) $jual->id_kas_bank,
                    tanggal:          $jual->tanggal_transaksi,
                );
            }

            DB::table('penjualan')->where('id_penjualan', $idPenjualan)->update([
                'total_hpp'            => $totalHpp,
                'ada_baris_konsinyasi' => $barisKonsinyasi->isNotEmpty(),
                'status'               => 'selesai',
                'status_posting'       => 'T',
                'id_jurnal'            => $idJurnalUtama,
                'updated_at'           => now(),
            ]);
        });
    }
}
