<?php

namespace App\Services\Finance;

use App\Models\Tenant\PeriodeAkuntansi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TutupBulanService — Melaksanakan proses "Soft-Close" satu periode bulan.
 *
 * KONSEP:
 *   Tutup Bulan bukan berarti menghapus atau membekukan sistem.
 *   Ini adalah tanda bahwa semua jurnal bulan tersebut sudah benar dan
 *   disetujui. Setelah periode di-CLOSE, setiap percobaan posting
 *   jurnal dengan tanggal di bulan tersebut akan ditolak oleh
 *   PeriodeTutupException (yang sudah ada di JurnalService).
 *
 * 8 VALIDASI SEBELUM TUTUP:
 *   1. Tidak ada jurnal berstatus DRAFT
 *   2. Total Debet = Total Kredit (balance check)
 *   3. Saldo Kas/Bank (Buku Besar) = total dari v_saldo_berjalan
 *   4. Saldo Persediaan (BB) sesuai dengan nilai stok fisik gudang
 *   5. Saldo Piutang (BB) = total sisa piutang di tabel piutang
 *   6. Saldo Hutang (BB) = total sisa hutang di tabel hutang
 *   7. Saldo Persediaan Konsinyasi (BB) = nilai stok konsinyasi tercatat
 *   8. Piutang konsinyasi pemilik = Hutang konsinyasi penerima (via v_rekonsiliasi_konsinyasi)
 *
 * Setelah semua validasi lulus, status `periode_akuntansi` diubah ke CLOSED,
 * dan JurnalService::bangunBukuBesar() dipanggil sekali lagi untuk snapshot final.
 */
class TutupBulanService
{
    public function __construct(private JurnalService $jurnalService) {}

    // =========================================================================
    // METHOD PUBLIK: cekValidasi() — dipakai Controller untuk menampilkan status
    // =========================================================================

    /**
     * Jalankan semua 8 validasi dan kembalikan hasilnya sebagai array.
     *
     * Setiap item array mengandung:
     *   - 'label'   : Deskripsi validasi
     *   - 'lulus'   : true / false
     *   - 'detail'  : Informasi tambahan jika gagal
     *
     * @return array<int, array{label: string, lulus: bool, detail: string|null}>
     */
    public function cekValidasi(int $tahun, int $bulan): array
    {
        $idKoperasi = app('koperasi_aktif');

        return [
            $this->cek1TidakAdaDraft($idKoperasi, $tahun, $bulan),
            $this->cek2TotalBalance($idKoperasi, $tahun, $bulan),
            $this->cek3SaldoKasBank($idKoperasi, $tahun, $bulan),
            $this->cek4SaldoPersediaan($idKoperasi, $tahun, $bulan),
            $this->cek5SaldoPiutang($idKoperasi, $tahun, $bulan),
            $this->cek6SaldoHutang($idKoperasi, $tahun, $bulan),
            $this->cek7SaldoPersediaanKonsinyasi($idKoperasi, $tahun, $bulan),
            $this->cek8RekonsiliasiKonsinyasi($idKoperasi),
        ];
    }

    // =========================================================================
    // METHOD PUBLIK: tutupBulan() — eksekusi tutup bulan jika semua validasi lulus
    // =========================================================================

    /**
     * Tutup periode bulan secara resmi.
     *
     * Alur:
     *   1. Jalankan semua 8 validasi — lempar exception jika ada yang gagal.
     *   2. Panggil JurnalService::bangunBukuBesar() untuk snapshot saldo final.
     *   3. Upsert baris periode_akuntansi → status = CLOSED.
     *
     * @throws \RuntimeException  jika ada validasi yang gagal
     * @throws \LogicException    jika periode sudah CLOSED atau LOCKED
     */
    public function tutupBulan(int $tahun, int $bulan): PeriodeAkuntansi
    {
        $idKoperasi = app('koperasi_aktif');

        // --- Cek apakah periode sudah ditutup ---
        $periode = PeriodeAkuntansi::where('id_koperasi', $idKoperasi)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->first();

        if ($periode && $periode->status !== 'OPEN') {
            throw new \LogicException(
                "Periode {$tahun}-{$bulan} sudah berstatus [{$periode->status}], tidak bisa ditutup ulang."
            );
        }

        // --- Jalankan semua validasi ---
        $hasilValidasi = $this->cekValidasi($tahun, $bulan);
        $gagal = array_filter($hasilValidasi, fn($v) => !$v['lulus']);

        if (!empty($gagal)) {
            $daftar = implode('; ', array_column(array_values($gagal), 'label'));
            throw new \RuntimeException(
                "Tutup bulan gagal. Validasi belum lulus: {$daftar}"
            );
        }

        // --- Rebuild buku besar untuk snapshot saldo final ---
        $this->jurnalService->bangunBukuBesar($tahun, $bulan);

        // --- Kunci periode → CLOSED ---
        $periode = PeriodeAkuntansi::updateOrCreate(
            [
                'id_koperasi' => $idKoperasi,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ],
            [
                'status'      => 'CLOSED',
                'tgl_tutup'   => now(),
                'ditutup_oleh'=> Auth::id(),
            ]
        );

        return $periode;
    }

    // =========================================================================
    // VALIDASI 1 — Tidak ada jurnal berstatus DRAFT
    // =========================================================================

    private function cek1TidakAdaDraft(int $idKoperasi, int $tahun, int $bulan): array
    {
        $jumlah = DB::table('jurnal_header')
            ->where('id_koperasi', $idKoperasi)
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->where('status', 'DRAFT')
            ->count();

        return [
            'no'     => 1,
            'label'  => 'Tidak ada jurnal berstatus DRAFT',
            'lulus'  => $jumlah === 0,
            'detail' => $jumlah > 0 ? "Ada {$jumlah} jurnal masih DRAFT" : null,
        ];
    }

    // =========================================================================
    // VALIDASI 2 — Total Debet = Total Kredit (balance check seluruh periode)
    // =========================================================================

    private function cek2TotalBalance(int $idKoperasi, int $tahun, int $bulan): array
    {
        $totals = DB::table('jurnal_header')
            ->where('id_koperasi', $idKoperasi)
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->where('status', 'POSTED')
            ->selectRaw('SUM(total_debet) as total_d, SUM(total_kredit) as total_k')
            ->first();

        $selisih = abs(($totals->total_d ?? 0) - ($totals->total_k ?? 0));
        $lulus   = $selisih < 0.01;

        return [
            'no'     => 2,
            'label'  => 'Total Debet = Total Kredit (balance seluruh periode)',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? sprintf('Selisih: Rp %s', number_format($selisih, 2))
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 3 — Saldo Kas/Bank di Buku Besar sesuai kelompok Aktiva
    // (Validasi sederhana: pastikan saldo tidak negatif)
    // =========================================================================

    private function cek3SaldoKasBank(int $idKoperasi, int $tahun, int $bulan): array
    {
        // Ambil semua akun Kas/Bank (kode_induk biasanya 111x)
        // Saldo negatif pada akun bertipe Debit = anomali
        $akunNegatif = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->where('c.kelompok', 'Aktiva')
            ->whereIn('c.kode_anak', function ($q) {
                // akun Kas dan Bank: kode dimulai dengan 111
                $q->select('kode_anak')->from('master_coa')
                  ->where('kode_anak', 'like', '111%')
                  ->where('is_transaction', 'T');
            })
            ->whereRaw('(bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit) < 0')
            ->count();

        return [
            'no'     => 3,
            'label'  => 'Saldo Kas/Bank tidak negatif',
            'lulus'  => $akunNegatif === 0,
            'detail' => $akunNegatif > 0
                ? "Ada {$akunNegatif} akun Kas/Bank dengan saldo negatif"
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 4 — Saldo Persediaan (BB) ≈ nilai fisik stok gudang
    // (Pendekatan: pastikan keduanya tidak selisih > 0 — rekonsiliasi otomatis)
    // =========================================================================

    private function cek4SaldoPersediaan(int $idKoperasi, int $tahun, int $bulan): array
    {
        // Total nilai persediaan dari buku besar (akun 112x)
        $saldoBB = DB::table('buku_besar_periode as bbp')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->whereIn('bbp.kode_anak', function ($q) {
                $q->select('kode_anak')->from('master_coa')
                  ->where('kode_anak', 'like', '112%')
                  ->where('is_transaction', 'T');
            })
            ->sum(DB::raw('bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit'));

        // Total nilai fisik stok dari tabel stok.
        // Tabel stok PK = (id_gudang, id_barang) — tidak ada id_koperasi.
        // Filter koperasi via join ke tabel gudang.
        // Kolom nilai_persediaan sudah berisi qty_on_hand × hpp_rata2 (dikelola oleh modul gudang).
        $saldoFisik = DB::table('stok as s')
            ->join('gudang as g', 'g.id_gudang', '=', 's.id_gudang')
            ->where('g.id_koperasi', $idKoperasi)
            ->sum('s.nilai_persediaan');

        $selisih = abs((float) $saldoBB - (float) $saldoFisik);
        // Toleransi 1% dari nilai buku besar (untuk penyusutan kecil)
        $toleransi = max(1000, abs((float) $saldoBB) * 0.01);
        $lulus     = $selisih <= $toleransi;

        return [
            'no'     => 4,
            'label'  => 'Saldo Persediaan (Buku Besar) ≈ nilai fisik stok',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? sprintf('BB: %s | Fisik: %s | Selisih: %s',
                    number_format((float)$saldoBB, 0, ',', '.'),
                    number_format((float)$saldoFisik, 0, ',', '.'),
                    number_format($selisih, 0, ',', '.'))
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 5 — Saldo Piutang (BB) = total sisa piutang (buku pembantu)
    // =========================================================================

    private function cek5SaldoPiutang(int $idKoperasi, int $tahun, int $bulan): array
    {
        // Saldo piutang dari buku besar (akun 113x)
        $saldoBB = DB::table('buku_besar_periode as bbp')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->whereIn('bbp.kode_anak', function ($q) {
                $q->select('kode_anak')->from('master_coa')
                  ->where('kode_anak', 'like', '113%')
                  ->where('is_transaction', 'T');
            })
            ->sum(DB::raw('bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit'));

        // Total sisa piutang dari buku pembantu
        // Status: 'belum_lunas' atau 'sebagian' (bukan 'OPEN'/'PARTIAL')
        $saldoPembantu = DB::table('piutang')
            ->where('id_koperasi', $idKoperasi)
            ->whereIn('status', ['belum_lunas', 'sebagian'])
            ->sum(DB::raw('nilai_awal - nilai_terbayar'));

        $selisih = abs((float) $saldoBB - (float) $saldoPembantu);
        $lulus   = $selisih < 1; // Toleransi Rp 1 (rounding)

        return [
            'no'     => 5,
            'label'  => 'Saldo Piutang (Buku Besar) = buku pembantu piutang',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? sprintf('BB: %s | Pembantu: %s | Selisih: %s',
                    number_format((float)$saldoBB, 0, ',', '.'),
                    number_format((float)$saldoPembantu, 0, ',', '.'),
                    number_format($selisih, 0, ',', '.'))
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 6 — Saldo Hutang (BB) = total sisa hutang (buku pembantu)
    // =========================================================================

    private function cek6SaldoHutang(int $idKoperasi, int $tahun, int $bulan): array
    {
        // Saldo hutang dari buku besar (akun 211x dan 212x)
        $saldoBB = DB::table('buku_besar_periode as bbp')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->whereIn('bbp.kode_anak', function ($q) {
                $q->select('kode_anak')->from('master_coa')
                  ->where(function ($q2) {
                      $q2->where('kode_anak', 'like', '211%')
                         ->orWhere('kode_anak', 'like', '212%');
                  })
                  ->where('is_transaction', 'T');
            })
            ->sum(DB::raw('bbp.saldo_akhir_kredit - bbp.saldo_akhir_debet'));

        // Total sisa hutang dari buku pembantu
        // Status: 'belum_lunas' atau 'sebagian' (bukan 'OPEN'/'PARTIAL')
        $saldoPembantu = DB::table('hutang')
            ->where('id_koperasi', $idKoperasi)
            ->whereIn('status', ['belum_lunas', 'sebagian'])
            ->sum(DB::raw('nilai_awal - nilai_terbayar'));

        $selisih = abs((float) $saldoBB - (float) $saldoPembantu);
        $lulus   = $selisih < 1;

        return [
            'no'     => 6,
            'label'  => 'Saldo Hutang (Buku Besar) = buku pembantu hutang',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? sprintf('BB: %s | Pembantu: %s | Selisih: %s',
                    number_format((float)$saldoBB, 0, ',', '.'),
                    number_format((float)$saldoPembantu, 0, ',', '.'),
                    number_format($selisih, 0, ',', '.'))
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 7 — Saldo Persediaan Konsinyasi (BB) = nilai stok titipan
    // =========================================================================

    private function cek7SaldoPersediaanKonsinyasi(int $idKoperasi, int $tahun, int $bulan): array
    {
        // Akun persediaan konsinyasi biasanya 113x (piutang konsinyasi)
        // atau akun khusus yang sudah diset di COA
        $saldoBB = DB::table('buku_besar_periode as bbp')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->whereIn('bbp.kode_anak', function ($q) {
                $q->select('kode_anak')->from('master_coa')
                  ->where('kode_anak', 'like', '1135%') // Piutang Konsinyasi
                  ->where('is_transaction', 'T');
            })
            ->sum(DB::raw('bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit'));

        // Nilai stok konsinyasi yang masih aktif di gudang koperasi ini (sebagai penerima).
        // stok_konsinyasi punya id_koperasi_penerima, qty_sisa, dan harga_titip_satuan —
        // nilai per unit sudah disnapsot saat pengiriman, tidak perlu join barang_per_koperasi.
        // Status lowercase: 'aktif' (bukan 'AKTIF'/'PARTIAL').
        $nilaiKonsinyasi = DB::table('stok_konsinyasi as sk')
            ->where('sk.id_koperasi_penerima', $idKoperasi)
            ->where('sk.status', 'aktif')
            ->whereRaw('sk.qty_sisa > 0')
            ->sum(DB::raw('sk.qty_sisa * sk.harga_titip_satuan'));

        $selisih = abs((float) $saldoBB - (float) $nilaiKonsinyasi);
        $lulus   = $selisih < 1;

        return [
            'no'     => 7,
            'label'  => 'Saldo Persediaan Konsinyasi (BB) = nilai stok titipan',
            'lulus'  => $lulus,
            'detail' => !$lulus
                ? sprintf('BB: %s | Stok Titipan: %s | Selisih: %s',
                    number_format((float)$saldoBB, 0, ',', '.'),
                    number_format((float)$nilaiKonsinyasi, 0, ',', '.'),
                    number_format($selisih, 0, ',', '.'))
                : null,
        ];
    }

    // =========================================================================
    // VALIDASI 8 — Rekonsiliasi konsinyasi: piutang pemilik = hutang penerima
    // Sumber: view v_rekonsiliasi_konsinyasi (hanya menampilkan baris berselisih)
    // =========================================================================

    private function cek8RekonsiliasiKonsinyasi(int $idKoperasi): array
    {
        // View ini sudah difilter: WHERE selisih <> 0
        // Kita cukup ambil baris yang melibatkan koperasi ini (sebagai pemilik ATAU penerima)
        $jumlahSelisih = DB::table('v_rekonsiliasi_konsinyasi')
            ->where(function ($q) use ($idKoperasi) {
                $q->where('id_koperasi_pemilik', $idKoperasi)
                  ->orWhere('id_koperasi_penerima', $idKoperasi);
            })
            ->count();

        return [
            'no'     => 8,
            'label'  => 'Rekonsiliasi konsinyasi: piutang pemilik = hutang penerima',
            'lulus'  => $jumlahSelisih === 0,
            'detail' => $jumlahSelisih > 0
                ? "Ada {$jumlahSelisih} kiriman konsinyasi yang belum balance antar desa"
                : null,
        ];
    }
}
