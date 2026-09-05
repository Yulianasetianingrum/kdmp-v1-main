<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SATU-SATUNYA service yang boleh menyentuh tabel `stok` dan `kartu_stok`.
 *
 * Metode penilaian: MOVING AVERAGE (perpetual).
 *   HPP_baru = (nilai_lama + nilai_masuk) / (qty_lama + qty_masuk)
 *
 * Barang konsinyasi TIDAK PERNAH lewat sini — pakai KonsinyasiService.
 */
class StokService
{
    private const SKALA_QTY  = 4;
    private const SKALA_UANG = 2;
    private const SKALA_HPP  = 4;

    /**
     * Barang masuk. Menghitung ulang hpp_rata2.
     *
     * @return string hpp_rata2 setelah mutasi
     */
    public function masuk(
        int $koperasiId,
        int $gudangId,
        int $barangId,
        string $qty,
        string $hargaSatuan,
        string $tanggal,
        string $refTipe,
        ?int $refId = null,
        ?int $idJurnal = null,
    ): string {
        return DB::transaction(function () use (
            $koperasiId, $gudangId, $barangId, $qty, $hargaSatuan,
            $tanggal, $refTipe, $refId, $idJurnal
        ) {
            $stok = $this->kunciStok($gudangId, $barangId);

            $nilaiMasuk  = bcmul($qty, $hargaSatuan, self::SKALA_UANG);
            $qtyBaru     = bcadd($stok->qty_on_hand, $qty, self::SKALA_QTY);
            $nilaiBaru   = bcadd($stok->nilai_persediaan, $nilaiMasuk, self::SKALA_UANG);

            $hppBaru = bccomp($qtyBaru, '0', self::SKALA_QTY) > 0
                ? bcdiv($nilaiBaru, $qtyBaru, self::SKALA_HPP)
                : '0';

            $this->simpanStok($gudangId, $barangId, $qtyBaru, $hppBaru, $nilaiBaru);

            $this->tulisKartu([
                'id_koperasi'       => $koperasiId,
                'id_gudang'         => $gudangId,
                'id_barang'         => $barangId,
                'tanggal'           => $tanggal,
                'jenis_mutasi'      => str_starts_with($refTipe, 'OPNAME') ? 'ADJ_IN' : 'IN',
                'ref_tipe'          => $refTipe,
                'ref_id'            => $refId,
                'qty_masuk'         => $qty,
                'qty_keluar'        => 0,
                'harga_satuan'      => $hargaSatuan,
                'nilai_mutasi'      => $nilaiMasuk,
                'saldo_qty'         => $qtyBaru,
                'saldo_nilai'       => $nilaiBaru,
                'hpp_rata2_setelah' => $hppBaru,
                'id_jurnal'         => $idJurnal,
            ]);

            return $hppBaru;
        });
    }

    /**
     * Barang keluar. Memakai hpp_rata2 yang berlaku, TIDAK mengubahnya.
     *
     * @return string nilai HPP total dari mutasi ini (untuk dipakai menjurnal)
     */
    public function keluar(
        int $koperasiId,
        int $gudangId,
        int $barangId,
        string $qty,
        string $tanggal,
        string $refTipe,
        ?int $refId = null,
        ?int $idJurnal = null,
        ?string $jenisKejadian = null,
    ): string {
        return DB::transaction(function () use (
            $koperasiId, $gudangId, $barangId, $qty, $tanggal,
            $refTipe, $refId, $idJurnal, $jenisKejadian
        ) {
            $stok = $this->kunciStok($gudangId, $barangId);

            if (bccomp($stok->qty_on_hand, $qty, self::SKALA_QTY) < 0) {
                throw new RuntimeException(
                    "Stok tidak cukup. Tersedia {$stok->qty_on_hand}, diminta {$qty}."
                );
            }

            $hpp        = $stok->hpp_rata2;
            $nilaiKeluar = bcmul($qty, $hpp, self::SKALA_UANG);
            $qtyBaru     = bcsub($stok->qty_on_hand, $qty, self::SKALA_QTY);
            $nilaiBaru   = bcsub($stok->nilai_persediaan, $nilaiKeluar, self::SKALA_UANG);

            // hpp_rata2 TIDAK berubah saat pengeluaran
            $this->simpanStok($gudangId, $barangId, $qtyBaru, $hpp, $nilaiBaru);

            $this->tulisKartu([
                'id_koperasi'       => $koperasiId,
                'id_gudang'         => $gudangId,
                'id_barang'         => $barangId,
                'tanggal'           => $tanggal,
                'jenis_mutasi'      => in_array($refTipe, ['OPNAME', 'KERUSAKAN'], true) ? 'ADJ_OUT' : 'OUT',
                'ref_tipe'          => $refTipe,
                'ref_id'            => $refId,
                'qty_masuk'         => 0,
                'qty_keluar'        => $qty,
                'harga_satuan'      => $hpp,
                'nilai_mutasi'      => $nilaiKeluar,
                'saldo_qty'         => $qtyBaru,
                'saldo_nilai'       => $nilaiBaru,
                'hpp_rata2_setelah' => $hpp,
                'jenis_kejadian'    => $jenisKejadian,
                'id_jurnal'         => $idJurnal,
            ]);

            return $nilaiKeluar;
        });
    }

    public function hppSaatIni(int $gudangId, int $barangId): string
    {
        return (string) (DB::table('stok')
            ->where('id_gudang', $gudangId)
            ->where('id_barang', $barangId)
            ->value('hpp_rata2') ?? '0');
    }

    public function tersedia(int $gudangId, int $barangId): string
    {
        $s = DB::table('stok')
            ->where('id_gudang', $gudangId)
            ->where('id_barang', $barangId)
            ->first();

        if (! $s) {
            return '0';
        }

        return bcsub($s->qty_on_hand, $s->qty_reserved, self::SKALA_QTY);
    }

    private function kunciStok(int $gudangId, int $barangId): object
    {
        $stok = DB::table('stok')
            ->where('id_gudang', $gudangId)
            ->where('id_barang', $barangId)
            ->lockForUpdate()
            ->first();

        if ($stok) {
            return $stok;
        }

        DB::table('stok')->insert([
            'id_gudang' => $gudangId, 'id_barang' => $barangId,
            'qty_on_hand' => 0, 'qty_reserved' => 0,
            'hpp_rata2' => 0, 'nilai_persediaan' => 0,
            'updated_at' => now(),
        ]);

        return DB::table('stok')
            ->where('id_gudang', $gudangId)
            ->where('id_barang', $barangId)
            ->lockForUpdate()
            ->first();
    }

    private function simpanStok(int $g, int $b, string $qty, string $hpp, string $nilai): void
    {
        DB::table('stok')->where('id_gudang', $g)->where('id_barang', $b)->update([
            'qty_on_hand'      => $qty,
            'hpp_rata2'        => $hpp,
            'nilai_persediaan' => $nilai,
            'updated_at'       => now(),
        ]);
    }

    /**
     * Unique key (ref_tipe, ref_id, id_barang, jenis_mutasi) mencegah
     * satu dokumen memotong stok dua kali.
     */
    private function tulisKartu(array $data): void
    {
        DB::table('kartu_stok')->insert($data + [
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
