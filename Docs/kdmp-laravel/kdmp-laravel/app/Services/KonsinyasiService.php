<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * KONSINYASI ANTAR DESA
 *
 * Prinsip: barang titipan TETAP MILIK desa pengirim sampai laku ke warga.
 * Baca docs/03-konsinyasi.md sebelum mengubah apa pun di sini.
 *
 * Empat aturan yang tidak boleh dilanggar:
 *   1. Saat kirim: tidak ada penjualan, tidak ada piutang/hutang
 *   2. Barang titipan TIDAK masuk tabel `stok` desa penerima
 *   3. Saat laku: omzet milik PEMILIK, penerima hanya mengakui imbalan
 *   4. Persediaan pemilik berkurang saat laku, bukan saat kirim
 */
class KonsinyasiService
{
    public function __construct(
        private JurnalService $jurnal,
        private StokService $stok,
    ) {}

    /**
     * PERISTIWA 1 — Desa pemilik mengirim titipan.
     *
     * Buku pemilik : Persediaan Konsinyasi (D) / Persediaan (K) senilai HPP
     * Buku penerima: TIDAK ADA JURNAL
     */
    public function kirim(int $idKiriman): void
    {
        DB::transaction(function () use ($idKiriman) {
            $k = DB::table('pengiriman_konsinyasi')->where('id_kiriman', $idKiriman)->lockForUpdate()->first();

            if (! $k) {
                throw new RuntimeException("Pengiriman {$idKiriman} tidak ditemukan.");
            }
            if ($k->status_posting === 'T') {
                throw new RuntimeException('Pengiriman ini sudah diposting.');
            }

            $detail = DB::table('pengiriman_konsinyasi_detail')->where('id_kiriman', $idKiriman)->get();
            if ($detail->isEmpty()) {
                throw new RuntimeException('Pengiriman tanpa detail barang.');
            }

            $totalHpp = '0';

            foreach ($detail as $d) {
                // Keluarkan dari stok normal desa pemilik
                $nilaiHpp = $this->stok->keluar(
                    koperasiId: $k->id_koperasi_pemilik,
                    gudangId:   $k->id_gudang_asal,
                    barangId:   $d->id_barang,
                    qty:        (string) $d->qty_dasar,
                    tanggal:    $k->tgl_kirim,
                    refTipe:    'KIRIM_KONSINYASI',
                    refId:      $idKiriman,
                );

                $totalHpp = bcadd($totalHpp, $nilaiHpp, 2);

                // Catat sebagai stok titipan di gudang penerima.
                // BUKAN di tabel `stok` — ini bukan aset penerima.
                DB::table('stok_konsinyasi')->insert([
                    'id_kiriman'            => $idKiriman,
                    'id_koperasi_pemilik'   => $k->id_koperasi_pemilik,
                    'id_koperasi_penerima'  => $k->id_koperasi_penerima,
                    'id_gudang_penerima'    => $k->id_gudang_tujuan,
                    'id_barang'             => $d->id_barang,
                    'qty_titip'             => $d->qty_dasar,
                    'qty_sisa'              => $d->qty_dasar,
                    'harga_titip_satuan'    => $d->harga_titip_satuan,
                    'harga_jual_satuan'     => $d->harga_jual_saran,
                    'hpp_pemilik'           => $d->hpp_pemilik,
                    'status'                => 'aktif',
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            // Jurnal HANYA di buku pemilik. Reklasifikasi aset, bukan penjualan.
            $idJurnal = $this->jurnal->posting(
                kodeTransaksi: 'KTK',
                koperasiId:    $k->id_koperasi_pemilik,
                tanggal:       $k->tgl_kirim,
                payload:       [
                    'total_hpp'     => $totalHpp,
                    'id_unit_usaha' => $this->unitUsahaDari($detail->first()->id_barang),
                ],
                sourceType: 'pengiriman_konsinyasi',
                sourceId:   $idKiriman,
                keterangan: "Kirim titipan {$k->kode_kiriman}",
            );

            DB::table('pengiriman_konsinyasi')->where('id_kiriman', $idKiriman)->update([
                'status'            => 'dikirim',
                'status_posting'    => 'T',
                'id_jurnal_kirim'   => $idJurnal,
                'total_hpp_pemilik' => $totalHpp,
                'updated_at'        => now(),
            ]);
        });
    }

    /**
     * PERISTIWA 2 & 3 — Penerima menjual titipan ke warga.
     * Dipanggil dari PenjualanService untuk setiap baris nota yang punya
     * id_stok_konsinyasi.
     *
     * Menghasilkan DUA jurnal di DUA pembukuan, dalam satu transaksi:
     *   Buku penerima (KJL): Kas D / Hutang Konsinyasi K / Imbalan K
     *   Buku pemilik  (KAP): Piutang Konsinyasi D / Pendapatan K
     *                        HPP Konsinyasi D / Persediaan Konsinyasi K
     */
    public function jualTitipan(
        int $idStokKonsinyasi,
        string $qty,
        string $hargaJualSatuan,
        int $idPenjualan,
        int $idKasBank,
        string $tanggal,
    ): array {
        $sk = DB::table('stok_konsinyasi')
            ->where('id_stok_konsinyasi', $idStokKonsinyasi)
            ->lockForUpdate()
            ->first();

        if (! $sk) {
            throw new RuntimeException("Stok konsinyasi {$idStokKonsinyasi} tidak ditemukan.");
        }
        if (bccomp((string) $sk->qty_sisa, $qty, 4) < 0) {
            throw new RuntimeException("Stok titipan tidak cukup. Sisa {$sk->qty_sisa}, diminta {$qty}.");
        }

        $kiriman = DB::table('pengiriman_konsinyasi')->where('id_kiriman', $sk->id_kiriman)->first();

        $totalBayar     = bcmul($qty, $hargaJualSatuan, 2);
        $hakPemilik     = bcmul($qty, (string) $sk->harga_titip_satuan, 2);
        $totalHppPemilik = bcmul($qty, (string) $sk->hpp_pemilik, 2);

        // Dua model imbalan
        if ($kiriman->model_imbalan === 'komisi_persen') {
            // Harga jual = harga titip. Imbalan dihitung dari persentase.
            $imbalan = bcdiv(bcmul($totalBayar, (string) $kiriman->persen_komisi, 4), '100', 2);
            $hakPemilik = bcsub($totalBayar, $imbalan, 2);
        } else {
            // selisih_harga: imbalan = harga jual - harga titip
            $imbalan = bcsub($totalBayar, $hakPemilik, 2);
            if (bccomp($imbalan, '0', 2) < 0) {
                throw new RuntimeException(
                    'Harga jual di bawah harga titip. Selisih negatif tidak diizinkan pada model selisih_harga.'
                );
            }
        }

        // --- Jurnal di buku PENERIMA ---
        $jurnalPenerima = $this->jurnal->posting(
            kodeTransaksi: 'KJL',
            koperasiId:    $sk->id_koperasi_penerima,
            tanggal:       $tanggal,
            payload:       [
                'total_bayar'       => $totalBayar,
                'nilai_hak_pemilik' => $hakPemilik,
                'nilai_imbalan'     => $imbalan,
                'id_kas_bank'       => $idKasBank,
            ],
            sourceType: 'detail_penjualan_konsinyasi',
            sourceId:   $idPenjualan,
            keterangan: "Jual titipan dari koperasi {$sk->id_koperasi_pemilik}",
        );

        // --- Jurnal di buku PEMILIK, serentak. Tidak menunggu laporan manual. ---
        $jurnalPemilik = $this->jurnal->posting(
            kodeTransaksi: 'KAP',
            koperasiId:    $sk->id_koperasi_pemilik,
            tanggal:       $tanggal,
            payload:       [
                'nilai_hak_pemilik' => $hakPemilik,
                'total_hpp'         => $totalHppPemilik,
            ],
            sourceType: 'detail_penjualan_konsinyasi',
            sourceId:   $idPenjualan,
            keterangan: "Pengakuan penjualan titipan di koperasi {$sk->id_koperasi_penerima}",
        );

        // Pada model komisi_persen, pemilik menanggung biaya imbalan
        if ($kiriman->model_imbalan === 'komisi_persen' && bccomp($imbalan, '0', 2) > 0) {
            $this->jurnal->posting(
                kodeTransaksi: 'KKM',
                koperasiId:    $sk->id_koperasi_pemilik,
                tanggal:       $tanggal,
                payload:       ['nilai_imbalan' => $imbalan],
                sourceType: 'detail_penjualan_konsinyasi_komisi',
                sourceId:   $idPenjualan,
            );
        }

        // Mutasi stok titipan
        DB::table('stok_konsinyasi')->where('id_stok_konsinyasi', $idStokKonsinyasi)->update([
            'qty_terjual' => bcadd((string) $sk->qty_terjual, $qty, 4),
            'qty_sisa'    => bcsub((string) $sk->qty_sisa, $qty, 4),
            'status'      => bccomp(bcsub((string) $sk->qty_sisa, $qty, 4), '0', 4) === 0 ? 'habis' : 'aktif',
            'updated_at'  => now(),
        ]);

        DB::table('kartu_konsinyasi')->insert([
            'id_stok_konsinyasi' => $idStokKonsinyasi,
            'tanggal'            => $tanggal,
            'jenis_mutasi'       => 'JUAL',
            'ref_tipe'           => 'PENJUALAN',
            'ref_id'             => $idPenjualan,
            'qty'                => $qty,
            'harga_titip_satuan' => $sk->harga_titip_satuan,
            'harga_jual_satuan'  => $hargaJualSatuan,
            'saldo_qty'          => bcsub((string) $sk->qty_sisa, $qty, 4),
            'id_jurnal_penerima' => $jurnalPenerima,
            'id_jurnal_pemilik'  => $jurnalPemilik,
            'created_at'         => now(),
        ]);

        // Buku pembantu: hutang di penerima, piutang di pemilik. HARUS cermin.
        $this->catatHutangPiutang($sk, $kiriman, $hakPemilik, $tanggal);

        return [
            'total_bayar' => $totalBayar,
            'hak_pemilik' => $hakPemilik,
            'imbalan'     => $imbalan,
        ];
    }

    /**
     * PERISTIWA 5 — Sisa titipan dikembalikan ke pemilik.
     * Buku pemilik : Persediaan (D) / Persediaan Konsinyasi (K)
     * Buku penerima: tidak ada jurnal
     */
    public function retur(int $idStokKonsinyasi, string $qty, string $tanggal): void
    {
        DB::transaction(function () use ($idStokKonsinyasi, $qty, $tanggal) {
            $sk = DB::table('stok_konsinyasi')
                ->where('id_stok_konsinyasi', $idStokKonsinyasi)->lockForUpdate()->first();

            if (bccomp((string) $sk->qty_sisa, $qty, 4) < 0) {
                throw new RuntimeException('Qty retur melebihi sisa titipan.');
            }

            $kiriman = DB::table('pengiriman_konsinyasi')->where('id_kiriman', $sk->id_kiriman)->first();

            // Barang masuk kembali ke stok normal pemilik, pada HPP yang sama
            $this->stok->masuk(
                koperasiId:  $sk->id_koperasi_pemilik,
                gudangId:    $kiriman->id_gudang_asal,
                barangId:    $sk->id_barang,
                qty:         $qty,
                hargaSatuan: (string) $sk->hpp_pemilik,
                tanggal:     $tanggal,
                refTipe:     'RETUR_KONSINYASI',
                refId:       $idStokKonsinyasi,
            );

            $totalHpp = bcmul($qty, (string) $sk->hpp_pemilik, 2);

            $this->jurnal->posting(
                kodeTransaksi: 'KRT',
                koperasiId:    $sk->id_koperasi_pemilik,
                tanggal:       $tanggal,
                payload:       [
                    'total_hpp'     => $totalHpp,
                    'id_unit_usaha' => $this->unitUsahaDari($sk->id_barang),
                ],
                sourceType: 'retur_konsinyasi',
                sourceId:   $idStokKonsinyasi,
            );

            DB::table('stok_konsinyasi')->where('id_stok_konsinyasi', $idStokKonsinyasi)->update([
                'qty_retur'  => bcadd((string) $sk->qty_retur, $qty, 4),
                'qty_sisa'   => bcsub((string) $sk->qty_sisa, $qty, 4),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * PERISTIWA 4 — Penerima menyetor hasil ke pemilik.
     * Dua jurnal cermin di dua pembukuan.
     */
    public function setor(int $idSetoran): void
    {
        DB::transaction(function () use ($idSetoran) {
            $s = DB::table('setoran_konsinyasi')->where('id_setoran', $idSetoran)->lockForUpdate()->first();

            if ($s->status_posting === 'T') {
                throw new RuntimeException('Setoran sudah diposting.');
            }

            $jPenyetor = $this->jurnal->posting(
                kodeTransaksi: 'KST',
                koperasiId:    $s->id_koperasi_penyetor,
                tanggal:       $s->tanggal,
                payload:       ['total_nilai' => $s->total_nilai, 'id_kas_bank' => $s->id_kas_bank_penyetor],
                sourceType: 'setoran_konsinyasi',
                sourceId:   $idSetoran,
            );

            $jPenerima = $this->jurnal->posting(
                kodeTransaksi: 'KTR',
                koperasiId:    $s->id_koperasi_penerima_dana,
                tanggal:       $s->tanggal,
                payload:       ['total_nilai' => $s->total_nilai, 'id_kas_bank' => $s->id_kas_bank_penerima],
                sourceType: 'setoran_konsinyasi',
                sourceId:   $idSetoran,
            );

            DB::table('setoran_konsinyasi')->where('id_setoran', $idSetoran)->update([
                'status_posting'     => 'T',
                'id_jurnal_penyetor' => $jPenyetor,
                'id_jurnal_penerima' => $jPenerima,
            ]);
        });
    }

    /**
     * Susut barang titipan. Penanggungnya ditetapkan di perjanjian,
     * bukan diputuskan operator per kasus.
     */
    public function susut(int $idStokKonsinyasi, string $qty, string $tanggal): void
    {
        $sk = DB::table('stok_konsinyasi')->where('id_stok_konsinyasi', $idStokKonsinyasi)->first();
        $kiriman = DB::table('pengiriman_konsinyasi')->where('id_kiriman', $sk->id_kiriman)->first();

        if ($kiriman->penanggung_susut === 'pemilik') {
            $nilai = bcmul($qty, (string) $sk->hpp_pemilik, 2);
            $this->jurnal->posting('KSP', $sk->id_koperasi_pemilik, $tanggal,
                ['nilai_susut' => $nilai], 'susut_konsinyasi', $idStokKonsinyasi);
        } else {
            // Penerima menanggung senilai harga titip, jadi hutangnya bertambah
            $nilai = bcmul($qty, (string) $sk->harga_titip_satuan, 2);
            $this->jurnal->posting('KSN', $sk->id_koperasi_penerima, $tanggal,
                ['nilai_susut' => $nilai], 'susut_konsinyasi', $idStokKonsinyasi);
        }

        DB::table('stok_konsinyasi')->where('id_stok_konsinyasi', $idStokKonsinyasi)->update([
            'qty_susut'  => bcadd((string) $sk->qty_susut, $qty, 4),
            'qty_sisa'   => bcsub((string) $sk->qty_sisa, $qty, 4),
            'updated_at' => now(),
        ]);
    }

    private function catatHutangPiutang(object $sk, object $kiriman, string $nilai, string $tanggal): void
    {
        // Penerima: hutang bertambah
        $this->tambahSaldo('hutang', $sk->id_koperasi_penerima, $sk->id_kiriman, '2117', $nilai, $tanggal, $kiriman);
        // Pemilik: piutang bertambah. Kedua angka ini WAJIB selalu sama.
        $this->tambahSaldo('piutang', $sk->id_koperasi_pemilik, $sk->id_kiriman, '1135', $nilai, $tanggal, $kiriman);
    }

    private function tambahSaldo(
        string $tabel, int $koperasiId, int $kirimanId,
        string $akun, string $nilai, string $tanggal, object $kiriman
    ): void {
        $pk = $tabel === 'hutang' ? 'id_hutang' : 'id_piutang';

        $row = DB::table($tabel)
            ->where('sumber_tipe', 'KONSINYASI')
            ->where('sumber_id', $kirimanId)
            ->lockForUpdate()
            ->first();

        if ($row) {
            DB::table($tabel)->where($pk, $row->$pk)->update([
                'nilai_awal' => bcadd((string) $row->nilai_awal, $nilai, 2),
            ]);

            return;
        }

        // id_pihak: mitra koperasi lawan, harus sudah terdaftar di master_pihak
        $idPihak = DB::table('master_pihak')
            ->where('id_koperasi', $koperasiId)
            ->where('jenis_pihak', 'koperasi_desa_lain')
            ->where('id_koperasi_mitra', $tabel === 'hutang'
                ? $kiriman->id_koperasi_pemilik
                : $kiriman->id_koperasi_penerima)
            ->value('id_pihak');

        if (! $idPihak) {
            throw new RuntimeException(
                'Koperasi mitra belum terdaftar di master_pihak. Daftarkan dulu sebelum konsinyasi.'
            );
        }

        DB::table($tabel)->insert([
            'id_koperasi'     => $koperasiId,
            'id_pihak'        => $idPihak,
            'sumber_tipe'     => 'KONSINYASI',
            'sumber_id'       => $kirimanId,
            'kode_akun'       => $akun,
            'tanggal'         => $tanggal,
            'tgl_jatuh_tempo' => $kiriman->tgl_batas_titip ?? $tanggal,
            'nilai_awal'      => $nilai,
            'nilai_terbayar'  => 0,
            'status'          => 'belum_lunas',
            'created_at'      => now(),
        ]);
    }

    private function unitUsahaDari(int $barangId): int
    {
        return (int) DB::table('master_barang')->where('id_barang', $barangId)->value('id_unit_usaha');
    }
}
