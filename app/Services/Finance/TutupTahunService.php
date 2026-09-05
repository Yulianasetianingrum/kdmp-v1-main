<?php

namespace App\Services\Finance;

use App\Models\Tenant\PeriodeAkuntansi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TutupTahunService — Melaksanakan proses "Hard-Close" satu tahun buku.
 *
 * KONSEP (dari KONSEP-TUTUP-BUKU.md):
 *   Tutup Tahun bukan mematikan sistem kasir.
 *   Sistem membedakan "Tahun Buku" dan "Waktu Real-time" via tanggal_transaksi.
 *   Kasir tetap berjualan di tahun baru, sementara akuntan mendapat
 *   Grace Period (hingga bulan Maret) untuk memasukkan jurnal penyesuaian
 *   backdate ke 31 Desember tahun lalu.
 *
 * ALUR EKSEKUSI (dipanggil oleh TutupTahunController):
 *   1. Validasi: config_shu total persentase = 100%
 *   2. Validasi: bulan 1-11 semua sudah CLOSED
 *   3. Validasi: bulan 12 masih OPEN (belum di-hard-close sebelumnya)
 *   4. Panggil sp_tutup_tahun → buat Jurnal Penutup otomatis
 *   5. Kunci semua periode tahun tersebut → LOCKED (tidak bisa dibuka)
 *
 * AKUN IKHTISAR (Akun 811):
 *   Jurnal Penutup menggunakan akun "Ikhtisar Laba Rugi" (811) sebagai
 *   clearing account. Setelah semua akun nominal nol, saldo 811 adalah
 *   Laba/Rugi bersih yang kemudian dipindah ke Modal/SHU sesuai config_shu.
 *
 * KEPUTUSAN DESAIN (grace period backdate):
 *   - Baris `konfigurasi` dengan kunci `grace_period_bulan` menentukan
 *     berapa bulan di tahun baru akuntan masih boleh backdate ke tahun lalu.
 *   - Default: 3 bulan (sampai akhir Maret).
 *   - Jika kasir mencoba jurnal di tahun lama setelah grace period habis,
 *     PeriodeTutupException dilempar otomatis oleh JurnalService.
 */
class TutupTahunService
{
    // =========================================================================
    // METHOD PUBLIK: cekPraKondisi() — dipakai Controller untuk tampilkan status
    // =========================================================================

    /**
     * Periksa semua pra-kondisi sebelum tombol Finalisasi bisa ditekan.
     *
     * @return array<int, array{label: string, lulus: bool, detail: string|null}>
     */
    public function cekPraKondisi(int $tahun): array
    {
        $idKoperasi = app('koperasi_aktif');

        return [
            $this->cekTotalPersentaseShu($idKoperasi, $tahun),
            $this->cekBulan1Sampai11Closed($idKoperasi, $tahun),
            $this->cekBulan12BelumLocked($idKoperasi, $tahun),
        ];
    }

    // =========================================================================
    // METHOD PUBLIK: tutupTahun() — eksekusi hard-close
    // =========================================================================

    /**
     * Finalisasi tutup tahun. Proses ini PERMANEN.
     *
     * @throws \RuntimeException  jika pra-kondisi belum terpenuhi
     * @throws \LogicException    jika tahun ini sudah pernah di-locked
     */
    public function tutupTahun(int $tahun): void
    {
        $idKoperasi = app('koperasi_aktif');

        // --- Cek apakah sudah pernah di-locked ---
        $sudahLocked = PeriodeAkuntansi::where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->where('status', 'LOCKED')
            ->exists();

        if ($sudahLocked) {
            throw new \LogicException(
                "Tahun buku {$tahun} sudah pernah difinalisasi (LOCKED) dan tidak bisa diulang."
            );
        }

        // --- Periksa semua pra-kondisi ---
        $praKondisi = $this->cekPraKondisi($tahun);
        $gagal = array_filter($praKondisi, fn($v) => !$v['lulus']);

        if (!empty($gagal)) {
            $daftar = implode('; ', array_column(array_values($gagal), 'label'));
            throw new \RuntimeException(
                "Tutup tahun gagal. Pra-kondisi belum terpenuhi: {$daftar}"
            );
        }

        DB::transaction(function () use ($idKoperasi, $tahun) {
            // --- Langkah 1: Panggil SP untuk membuat Jurnal Penutup ---
            // SP akan:
            //   a. Hitung total Pendapatan (kredit kelompok 4xx)
            //   b. Hitung total Biaya + HPP (debet kelompok 5xx)
            //   c. Buat Jurnal Penutup tanggal 31-Des menggunakan akun 811 (Ikhtisar)
            //   d. Pindahkan saldo 811 ke Modal/SHU sesuai config_shu
            DB::statement('CALL sp_tutup_tahun(?, ?, ?)', [
                $idKoperasi,
                $tahun,
                Auth::id(),
            ]);

            // --- Langkah 2: Kunci semua periode tahun ini → LOCKED ---
            // Termasuk bulan 13 (periode penyesuaian) jika ada.
            // LOCKED berarti tidak bisa dibuka kembali (lebih ketat dari CLOSED).
            PeriodeAkuntansi::where('id_koperasi', $idKoperasi)
                ->where('tahun', $tahun)
                ->update([
                    'status'       => 'LOCKED',
                    'tgl_tutup'    => now(),
                    'ditutup_oleh' => Auth::id(),
                ]);

            // --- Langkah 3: Pastikan semua bulan (1-12) punya baris LOCKED ---
            // Jika ada bulan yang belum pernah punya transaksi, baris periode_akuntansi
            // belum ada. Buat semua agar konsisten.
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                PeriodeAkuntansi::updateOrCreate(
                    [
                        'id_koperasi' => $idKoperasi,
                        'tahun'       => $tahun,
                        'bulan'       => $bulan,
                    ],
                    [
                        'status'       => 'LOCKED',
                        'tgl_tutup'    => now(),
                        'ditutup_oleh' => Auth::id(),
                    ]
                );
            }
        });
    }

    // =========================================================================
    // HELPER: ringkasanLabaRugi() — dipakai view untuk preview sebelum finalisasi
    // =========================================================================

    /**
     * Hitung ringkasan laba/rugi tahun ini dari buku_besar_periode.
     * Dipakai di halaman konfirmasi tutup tahun untuk preview akuntan.
     *
     * @return array{total_pendapatan: float, total_biaya: float, laba_bersih: float}
     */
    public function ringkasanLabaRugi(int $tahun): array
    {
        $idKoperasi = app('koperasi_aktif');

        // Total Pendapatan: akun kelompok Pendapatan → saldo normal Kredit
        $totalPendapatan = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->whereIn('c.kelompok', ['Pendapatan', 'Non-Operasional'])
            ->where('c.posisi_normal', 'K')
            ->sum(DB::raw('bbp.saldo_akhir_kredit - bbp.saldo_akhir_debet'));

        // Total Biaya + HPP: akun kelompok Biaya dan HPP → saldo normal Debet
        $totalBiaya = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->whereIn('c.kelompok', ['Biaya', 'HPP'])
            ->where('c.posisi_normal', 'D')
            ->sum(DB::raw('bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit'));

        $labaBersih = (float) $totalPendapatan - (float) $totalBiaya;

        return [
            'total_pendapatan' => (float) $totalPendapatan,
            'total_biaya'      => (float) $totalBiaya,
            'laba_bersih'      => $labaBersih,
        ];
    }

    // =========================================================================
    // VALIDASI PRA-KONDISI TUTUP TAHUN
    // =========================================================================

    /** Pra-kondisi 1: Total persentase config_shu = 100% */
    private function cekTotalPersentaseShu(int $idKoperasi, int $tahun): array
    {
        $total = DB::table('config_shu')
            ->where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->sum('persentase');

        // Toleransi floating: 99.99 ~ 100.01
        $lulus = abs((float) $total - 100.0) < 0.02;

        return [
            'no'     => 1,
            'label'  => 'Konfigurasi SHU: total persentase = 100%',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? "Total persentase SHU saat ini: {$total}%. Harus tepat 100%."
                : null,
        ];
    }

    /** Pra-kondisi 2: Bulan 1 s/d 11 semua sudah CLOSED atau LOCKED */
    private function cekBulan1Sampai11Closed(int $idKoperasi, int $tahun): array
    {
        $belumTutup = DB::table('periode_akuntansi')
            ->where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->whereBetween('bulan', [1, 11])
            ->whereNotIn('status', ['CLOSED', 'LOCKED'])
            ->count();

        // Bulan yang belum punya baris = belum ditutup
        // Hitung berapa bulan yang SEHARUSNYA ada (1-11) tapi tidak ada barisnya
        $adaBaris = DB::table('periode_akuntansi')
            ->where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->whereBetween('bulan', [1, 11])
            ->count();

        $totalGagal = $belumTutup + (11 - $adaBaris);

        return [
            'no'     => 2,
            'label'  => 'Bulan Januari s/d November sudah ditutup (CLOSED)',
            'lulus'  => $totalGagal === 0,
            'detail' => $totalGagal > 0
                ? "Ada {$totalGagal} bulan yang belum ditutup"
                : null,
        ];
    }

    /** Pra-kondisi 3: Tahun ini belum pernah di-LOCKED (belum finalisasi) */
    private function cekBulan12BelumLocked(int $idKoperasi, int $tahun): array
    {
        $sudahLocked = PeriodeAkuntansi::where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->where('status', 'LOCKED')
            ->exists();

        return [
            'no'     => 3,
            'label'  => 'Tahun buku belum pernah difinalisasi (LOCKED)',
            'lulus'  => !$sudahLocked,
            'detail' => $sudahLocked
                ? "Tahun buku {$tahun} sudah di-LOCKED sebelumnya. Finalisasi tidak bisa diulang."
                : null,
        ];
    }
}
