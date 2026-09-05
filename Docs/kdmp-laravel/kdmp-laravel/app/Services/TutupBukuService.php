<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tutup bulan dan tutup tahun.
 *
 * Delapan validasi di tutupBulan() adalah pengaman terpenting seluruh sistem.
 * Kalau salah satu gagal, ada modul yang menulis stok / piutang / hutang
 * tanpa menjurnalnya, atau sebaliknya.
 */
class TutupBukuService
{
    public function __construct(
        private JurnalService $jurnal,
        private PeriodeService $periode,
    ) {}

    /** @return array<string> daftar pelanggaran; kosong berarti aman */
    public function validasiTutupBulan(int $koperasiId, int $tahun, int $bulan): array
    {
        $masalah = [];

        // 1. Tidak ada dokumen draft
        foreach (['penjualan', 'pembelian', 'penerimaan_barang'] as $tbl) {
            $n = DB::table($tbl)->where('id_koperasi', $koperasiId)->where('status', 'draft')->count();
            if ($n > 0) {
                $masalah[] = "Masih ada {$n} dokumen draft di tabel {$tbl}.";
            }
        }

        // 2. Semua jurnal POSTED
        $draft = DB::table('jurnal_header')
            ->where('id_koperasi', $koperasiId)
            ->where('periode_tahun', $tahun)->where('periode_bulan', $bulan)
            ->where('status', 'DRAFT')->count();
        if ($draft > 0) {
            $masalah[] = "Ada {$draft} jurnal berstatus DRAFT.";
        }

        // 3. Debet = Kredit
        $t = DB::table('jurnal_header')
            ->where('id_koperasi', $koperasiId)
            ->where('periode_tahun', $tahun)->where('periode_bulan', $bulan)
            ->where('status', 'POSTED')
            ->selectRaw('SUM(total_debet) d, SUM(total_kredit) k')->first();
        if (bccomp((string) ($t->d ?? 0), (string) ($t->k ?? 0), 2) !== 0) {
            $masalah[] = "Total Debet ({$t->d}) tidak sama dengan Total Kredit ({$t->k}).";
        }

        // 4. Persediaan di Buku Besar = nilai fisik di tabel stok
        $bbPersediaan = $this->saldoAkun($koperasiId, ['1121', '1122', '1123', '1124']);
        $fisik = DB::table('stok')
            ->join('gudang', 'gudang.id_gudang', '=', 'stok.id_gudang')
            ->where('gudang.id_koperasi', $koperasiId)
            ->sum('stok.nilai_persediaan');
        if (bccomp($bbPersediaan, (string) $fisik, 2) !== 0) {
            $masalah[] = "Persediaan: Buku Besar {$bbPersediaan} vs tabel stok {$fisik}.";
        }

        // 5. Piutang
        $bbPiutang = $this->saldoAkun($koperasiId, ['1132', '1135']);
        $bpPiutang = DB::table('piutang')->where('id_koperasi', $koperasiId)
            ->selectRaw('SUM(nilai_awal - nilai_terbayar) s')->value('s') ?? 0;
        if (bccomp($bbPiutang, (string) $bpPiutang, 2) !== 0) {
            $masalah[] = "Piutang: Buku Besar {$bbPiutang} vs buku pembantu {$bpPiutang}.";
        }

        // 6. Hutang
        $bbHutang = $this->saldoAkun($koperasiId, ['2111', '2117']);
        $bpHutang = DB::table('hutang')->where('id_koperasi', $koperasiId)
            ->selectRaw('SUM(nilai_awal - nilai_terbayar) s')->value('s') ?? 0;
        if (bccomp($bbHutang, (string) $bpHutang, 2) !== 0) {
            $masalah[] = "Hutang: Buku Besar {$bbHutang} vs buku pembantu {$bpHutang}.";
        }

        // 7. Persediaan Konsinyasi = sisa titipan x hpp
        $bbKons = $this->saldoAkun($koperasiId, ['1126']);
        $fisikKons = DB::table('stok_konsinyasi')
            ->where('id_koperasi_pemilik', $koperasiId)
            ->selectRaw('SUM(qty_sisa * hpp_pemilik) s')->value('s') ?? 0;
        if (bccomp($bbKons, (string) $fisikKons, 2) !== 0) {
            $masalah[] = "Persediaan Konsinyasi: Buku Besar {$bbKons} vs stok titipan {$fisikKons}.";
        }

        // 8. Piutang konsinyasi pemilik = hutang konsinyasi penerima
        $timpang = DB::table('v_rekonsiliasi_konsinyasi')
            ->where('id_koperasi_pemilik', $koperasiId)
            ->orWhere('id_koperasi_penerima', $koperasiId)
            ->count();
        if ($timpang > 0) {
            $masalah[] = "Ada {$timpang} pengiriman konsinyasi yang piutang dan hutangnya tidak cermin.";
        }

        return $masalah;
    }

    public function tutupBulan(int $koperasiId, int $tahun, int $bulan, int $userId): void
    {
        $masalah = $this->validasiTutupBulan($koperasiId, $tahun, $bulan);

        if ($masalah !== []) {
            throw new RuntimeException("Tutup bulan gagal:\n- ".implode("\n- ", $masalah));
        }

        DB::transaction(function () use ($koperasiId, $tahun, $bulan, $userId) {
            $this->bangunBukuBesar($koperasiId, $tahun, $bulan);
            $this->periode->tutup($koperasiId, $tahun, $bulan, $userId);
        });
    }

    /**
     * Bangun ulang buku_besar_periode dari jurnal.
     * Idempoten — boleh dijalankan berkali-kali.
     */
    public function bangunBukuBesar(int $koperasiId, int $tahun, int $bulan): void
    {
        [$thnLalu, $blnLalu] = $bulan === 1 ? [$tahun - 1, 12] : [$tahun, $bulan - 1];

        $mutasi = DB::table('jurnal_header as jh')
            ->join('jurnal_detail as jd', 'jd.id_jurnal', '=', 'jh.id_jurnal')
            ->where('jh.id_koperasi', $koperasiId)
            ->where('jh.periode_tahun', $tahun)
            ->where('jh.periode_bulan', $bulan)
            ->where('jh.status', 'POSTED')
            ->groupBy('jd.kode_anak')
            ->selectRaw('jd.kode_anak, SUM(jd.debet) d, SUM(jd.kredit) k')
            ->get();

        foreach ($mutasi as $m) {
            $awal = DB::table('buku_besar_periode')
                ->where('id_koperasi', $koperasiId)
                ->where('periode_tahun', $thnLalu)->where('periode_bulan', $blnLalu)
                ->where('kode_anak', $m->kode_anak)->first();

            $awalD = (string) ($awal->saldo_akhir_debet ?? 0);
            $awalK = (string) ($awal->saldo_akhir_kredit ?? 0);

            $posisi = DB::table('master_coa')->where('kode_anak', $m->kode_anak)->value('posisi_normal');

            // Akun D: saldo = awal + mutasi D - mutasi K
            // Akun K: saldo = awal + mutasi K - mutasi D
            $netto = $posisi === 'D'
                ? bcsub(bcadd($awalD, (string) $m->d, 2), (string) $m->k, 2)
                : bcsub(bcadd($awalK, (string) $m->k, 2), (string) $m->d, 2);

            DB::table('buku_besar_periode')->updateOrInsert(
                [
                    'id_koperasi'   => $koperasiId,
                    'periode_tahun' => $tahun,
                    'periode_bulan' => $bulan,
                    'kode_anak'     => $m->kode_anak,
                ],
                [
                    'saldo_awal_debet'   => $posisi === 'D' ? $awalD : 0,
                    'saldo_awal_kredit'  => $posisi === 'K' ? $awalK : 0,
                    'mutasi_debet'       => $m->d,
                    'mutasi_kredit'      => $m->k,
                    'saldo_akhir_debet'  => $posisi === 'D' ? $netto : 0,
                    'saldo_akhir_kredit' => $posisi === 'K' ? $netto : 0,
                    'dihitung_pada'      => now(),
                ]
            );
        }
    }

    /**
     * Tutup tahun. WAJIB: config_shu untuk tahun tersebut sudah terisi
     * dan totalnya 100%.
     */
    public function tutupTahun(int $koperasiId, int $tahun, int $userId): void
    {
        $total = DB::table('config_shu')
            ->where('id_koperasi', $koperasiId)->where('tahun', $tahun)
            ->sum('persentase');

        if (bccomp((string) $total, '100', 2) !== 0) {
            throw new RuntimeException(
                "Konfigurasi pembagian SHU tahun {$tahun} berjumlah {$total}%, harus 100%. ".
                'Isi dulu sesuai AD/ART koperasi.'
            );
        }

        // Langkah berikutnya: jurnal penutup 4,5,6,7 -> 811 -> 341,
        // lalu bagi 341 ke pos-pos config_shu, lalu kunci tahun.
        // Implementasi menyusul setelah persentase AD/ART tersedia.
        throw new RuntimeException('Jurnal penutup belum diimplementasikan. Lihat docs/02-alur-modul.md bagian M8.');
    }

    private function saldoAkun(int $koperasiId, array $akun): string
    {
        $r = DB::table('jurnal_header as jh')
            ->join('jurnal_detail as jd', 'jd.id_jurnal', '=', 'jh.id_jurnal')
            ->where('jh.id_koperasi', $koperasiId)
            ->where('jh.status', 'POSTED')
            ->whereIn('jd.kode_anak', $akun)
            ->selectRaw('SUM(jd.debet) d, SUM(jd.kredit) k')->first();

        return bcsub((string) ($r->d ?? 0), (string) ($r->k ?? 0), 2);
    }
}
