<?php

namespace App\Http\Controllers\Akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Akuntansi\Coa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LaporanController — Menyajikan 4 laporan keuangan standar:
 *
 *   1. neracaSaldo()  — Trial Balance: semua akun + saldo D/K per bulan
 *   2. bukuBesar()    — Ledger: mutasi per akun + running balance (dihitung PHP)
 *   3. labaRugi()     — Income Statement: Pendapatan − HPP − Biaya = Laba Bersih
 *   4. neraca()       — Balance Sheet: Aset = Kewajiban + Modal
 *
 * Semua bersumber dari buku_besar_periode (sudah teragregasi per bulan)
 * dan jurnal_header/detail (untuk detail buku besar).
 * Tidak ada tabel laporan terpisah — single source of truth = jurnal.
 */
class LaporanController extends Controller
{
    // =========================================================================
    // HELPER — parse filter tahun/bulan dari request
    // =========================================================================

    private function parsePeriode(Request $request): array
    {
        $tahun = (int) $request->query('tahun', date('Y'));
        $bulan = (int) $request->query('bulan', date('n'));
        $bulan = max(1, min(12, $bulan));
        return [$tahun, $bulan];
    }

    private function bulanLabel(int $bulan): string
    {
        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $labels[$bulan] ?? '-';
    }

    // =========================================================================
    // 1. NERACA SALDO (Trial Balance)
    // =========================================================================

    /**
     * Tampilkan semua akun dengan saldo Debet / Kredit per bulan yang dipilih.
     * Total Debet harus = Total Kredit — jika tidak, ada posting yang salah.
     *
     * Sumber data: buku_besar_periode JOIN master_coa
     * Dua tambahan Plan A:
     *   - Parent rollup: query GROUP BY dilakukan di PHP (tidak disimpan ke DB)
     */
    public function neracaSaldo(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');
        [$tahun, $bulan] = $this->parsePeriode($request);

        // Ambil semua akun yang punya data di periode ini
        $rows = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->orderBy('c.urutan_laporan')
            ->orderBy('c.kode_anak')
            ->select([
                'bbp.kode_anak',
                'c.nama_rekening',
                'c.kelompok',
                'c.posisi_normal',
                'bbp.saldo_akhir_debet',
                'bbp.saldo_akhir_kredit',
            ])
            ->get();

        // Hitung saldo normal per akun (untuk tampilan "saldo bersih")
        $rows = $rows->map(function ($row) {
            if ($row->posisi_normal === 'D') {
                $row->saldo_normal = $row->saldo_akhir_debet - $row->saldo_akhir_kredit;
                $row->saldo_d = max(0, $row->saldo_normal);
                $row->saldo_k = max(0, -$row->saldo_normal);
            } else {
                $row->saldo_normal = $row->saldo_akhir_kredit - $row->saldo_akhir_debet;
                $row->saldo_d = max(0, -$row->saldo_normal);
                $row->saldo_k = max(0, $row->saldo_normal);
            }
            return $row;
        });

        $totalDebet  = $rows->sum('saldo_d');
        $totalKredit = $rows->sum('saldo_k');
        $isBalance   = abs($totalDebet - $totalKredit) < 0.01;

        return view('akuntansi.laporan.neraca-saldo', compact(
            'rows', 'tahun', 'bulan', 'totalDebet', 'totalKredit', 'isBalance'
        ));
    }

    // =========================================================================
    // 2. BUKU BESAR (General Ledger)
    // =========================================================================

    /**
     * Tampilkan mutasi per akun: saldo awal → baris transaksi → saldo akhir.
     * Running balance dihitung di PHP (Plan A tambahan #1).
     *
     * Sumber: jurnal_detail JOIN jurnal_header WHERE status = POSTED
     */
    public function bukuBesar(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');
        [$tahun, $bulan] = $this->parsePeriode($request);
        $kodeAnak = $request->query('kode_anak', '');

        // Semua akun leaf (is_transaction = T) untuk dropdown pilih akun
        $akunList = Coa::where('is_transaction', 'T')
            ->where('is_active', true)
            ->orderBy('urutan_laporan')
            ->orderBy('kode_anak')
            ->get(['kode_anak', 'nama_rekening', 'kelompok', 'posisi_normal']);

        $mutasi      = collect();
        $saldoAwal   = 0;
        $akunDipilih = null;

        if ($kodeAnak) {
            $akunDipilih = $akunList->firstWhere('kode_anak', $kodeAnak);

            // Saldo awal: ambil saldo_akhir bulan sebelumnya dari buku_besar_periode
            $prevTahun  = ($bulan === 1) ? $tahun - 1 : $tahun;
            $prevBulan  = ($bulan === 1) ? 12 : $bulan - 1;

            $bbpSebelum = DB::table('buku_besar_periode')
                ->where('id_koperasi', $idKoperasi)
                ->where('periode_tahun', $prevTahun)
                ->where('periode_bulan', $prevBulan)
                ->where('kode_anak', $kodeAnak)
                ->first(['saldo_akhir_debet', 'saldo_akhir_kredit']);

            if ($bbpSebelum && $akunDipilih) {
                $saldoAwal = ($akunDipilih->posisi_normal === 'D')
                    ? (float) $bbpSebelum->saldo_akhir_debet - (float) $bbpSebelum->saldo_akhir_kredit
                    : (float) $bbpSebelum->saldo_akhir_kredit - (float) $bbpSebelum->saldo_akhir_debet;
            }

            // Ambil mutasi bulan ini dari jurnal
            $mutasi = DB::table('jurnal_detail as jd')
                ->join('jurnal_header as jh', 'jh.id_jurnal', '=', 'jd.id_jurnal')
                ->where('jh.id_koperasi', $idKoperasi)
                ->where('jh.status', 'POSTED')
                ->where('jh.periode_tahun', $tahun)
                ->where('jh.periode_bulan', $bulan)
                ->where('jd.kode_anak', $kodeAnak)
                ->orderBy('jh.tanggal_jurnal')
                ->orderBy('jh.posted_at')
                ->orderBy('jd.urutan')
                ->select([
                    'jh.tanggal_jurnal',
                    'jh.no_jurnal',
                    'jh.kode_transaksi',
                    'jh.keterangan',
                    'jd.debet',
                    'jd.kredit',
                ])
                ->get();

            // Plan A Tambahan #1: hitung running balance di PHP
            if ($akunDipilih) {
                $running = $saldoAwal;
                $mutasi = $mutasi->map(function ($row) use (&$running, $akunDipilih) {
                    if ($akunDipilih->posisi_normal === 'D') {
                        $running += ((float) $row->debet - (float) $row->kredit);
                    } else {
                        $running += ((float) $row->kredit - (float) $row->debet);
                    }
                    $row->saldo_berjalan = $running;
                    return $row;
                });
            }
        }

        return view('akuntansi.laporan.buku-besar', compact(
            'akunList', 'kodeAnak', 'akunDipilih',
            'mutasi', 'saldoAwal', 'tahun', 'bulan'
        ));
    }

    // =========================================================================
    // 3. LABA / RUGI (Income Statement)
    // =========================================================================

    /**
     * Tampilkan Pendapatan − HPP − Biaya = Laba Bersih untuk periode yang dipilih.
     *
     * Filter: tahun + bulan_dari s.d. bulan_sampai
     * Sumber: SUM(mutasi) dari buku_besar_periode (bukan saldo_akhir, agar
     *         range periode bisa dipilih bebas, mis. Q1 = Jan-Mar)
     */
    public function labaRugi(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');
        $tahun      = (int) $request->query('tahun', date('Y'));
        $bulanDari  = (int) $request->query('bulan_dari', 1);
        $bulanSampai= (int) $request->query('bulan_sampai', (int) date('n'));
        $bulanDari  = max(1, min(12, $bulanDari));
        $bulanSampai= max($bulanDari, min(12, $bulanSampai));

        $kelompokLR = ['Pendapatan', 'HPP', 'Biaya', 'Non-Operasional'];

        // SUM mutasi debet & kredit per akun untuk range bulan yang dipilih
        $rows = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->whereBetween('bbp.periode_bulan', [$bulanDari, $bulanSampai])
            ->whereIn('c.kelompok', $kelompokLR)
            ->groupBy('bbp.kode_anak', 'c.nama_rekening', 'c.kelompok', 'c.posisi_normal', 'c.urutan_laporan')
            ->orderBy('c.urutan_laporan')
            ->select([
                'bbp.kode_anak',
                'c.nama_rekening',
                'c.kelompok',
                'c.posisi_normal',
                DB::raw('SUM(bbp.mutasi_debet)  as mutasi_debet'),
                DB::raw('SUM(bbp.mutasi_kredit) as mutasi_kredit'),
            ])
            ->get()
            ->map(function ($row) {
                // Saldo normal = nilai yang "wajar" untuk kelompok ini
                $row->nilai = ($row->posisi_normal === 'K')
                    ? (float) $row->mutasi_kredit - (float) $row->mutasi_debet
                    : (float) $row->mutasi_debet  - (float) $row->mutasi_kredit;
                return $row;
            });

        // Kelompokkan per seksi laporan
        $pendapatan    = $rows->where('kelompok', 'Pendapatan');
        $hpp           = $rows->where('kelompok', 'HPP');
        $biaya         = $rows->where('kelompok', 'Biaya');
        $nonOperasional= $rows->where('kelompok', 'Non-Operasional');

        $totalPendapatan = $pendapatan->sum('nilai');
        $totalHPP        = $hpp->sum('nilai');
        $labaKotor       = $totalPendapatan - $totalHPP;
        $totalBiaya      = $biaya->sum('nilai');
        $labaOperasi     = $labaKotor - $totalBiaya;

        // Non-Operasional: bisa + (pendapatan) atau - (biaya)
        // Gunakan posisi_normal untuk tentukan arahnya
        $netNonOp = $nonOperasional->sum('nilai');
        $labaBersih = $labaOperasi + $netNonOp;

        return view('akuntansi.laporan.laba-rugi', compact(
            'pendapatan', 'hpp', 'biaya', 'nonOperasional',
            'totalPendapatan', 'totalHPP', 'labaKotor',
            'totalBiaya', 'labaOperasi', 'netNonOp', 'labaBersih',
            'tahun', 'bulanDari', 'bulanSampai'
        ));
    }

    // =========================================================================
    // 4. NERACA (Balance Sheet)
    // =========================================================================

    /**
     * Tampilkan posisi Aset = Kewajiban + Modal per akhir bulan yang dipilih.
     *
     * Sumber: saldo_akhir dari buku_besar_periode bulan yang dipilih.
     * saldo_akhir sudah kumulatif (SP menyimpan saldo_awal + mutasi = saldo_akhir).
     *
     * Plan A Tambahan #2: rollup ke parent dihitung di PHP via kode_induk.
     */
    public function neraca(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');
        [$tahun, $bulan] = $this->parsePeriode($request);

        $kelompokNeraca = ['Aktiva', 'Kewajiban', 'Modal'];

        // Ambil saldo_akhir per akun untuk bulan yang dipilih
        $rows = DB::table('buku_besar_periode as bbp')
            ->join('master_coa as c', 'c.kode_anak', '=', 'bbp.kode_anak')
            ->where('bbp.id_koperasi', $idKoperasi)
            ->where('bbp.periode_tahun', $tahun)
            ->where('bbp.periode_bulan', $bulan)
            ->whereIn('c.kelompok', $kelompokNeraca)
            ->orderBy('c.urutan_laporan')
            ->select([
                'bbp.kode_anak',
                'c.kode_induk',
                'c.nama_rekening',
                'c.kelompok',
                'c.posisi_normal',
                'c.level',
                'c.urutan_laporan',
                'bbp.saldo_akhir_debet',
                'bbp.saldo_akhir_kredit',
            ])
            ->get()
            ->map(function ($row) {
                $row->saldo = ($row->posisi_normal === 'D')
                    ? (float) $row->saldo_akhir_debet - (float) $row->saldo_akhir_kredit
                    : (float) $row->saldo_akhir_kredit - (float) $row->saldo_akhir_debet;
                return $row;
            });

        $aktiva    = $rows->where('kelompok', 'Aktiva');
        $kewajiban = $rows->where('kelompok', 'Kewajiban');
        $modal     = $rows->where('kelompok', 'Modal');

        $totalAktiva    = $aktiva->sum('saldo');
        $totalKewajiban = $kewajiban->sum('saldo');
        $totalModal     = $modal->sum('saldo');
        $totalPassiva   = $totalKewajiban + $totalModal;
        $isBalance      = abs($totalAktiva - $totalPassiva) < 0.01;

        return view('akuntansi.laporan.neraca', compact(
            'aktiva', 'kewajiban', 'modal',
            'totalAktiva', 'totalKewajiban', 'totalModal', 'totalPassiva',
            'isBalance', 'tahun', 'bulan'
        ));
    }

    // =========================================================================
    // 5. ARUS KAS — Belum Diimplementasi (butuh klasifikasi tambahan di COA)
    // =========================================================================

    public function arusKas(): View
    {
        return view('akuntansi.laporan.placeholder', [
            'judul' => 'Laporan Arus Kas',
            'pesan' => 'Laporan Arus Kas memerlukan klasifikasi tambahan di master_coa (operasi/investasi/pendanaan). Akan diimplementasi setelah struktur COA diperluas.',
        ]);
    }
}
