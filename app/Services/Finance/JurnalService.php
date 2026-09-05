<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\AkunTidakDitemukanException;
use App\Exceptions\Finance\JurnalSudahDibalikException;
use App\Exceptions\Finance\JurnalTidakBalanceException;
use App\Exceptions\Finance\PeriodeTutupException;
use App\Models\Akuntansi\JurnalHeader;
use App\Models\Akuntansi\MasterDetailTransaksi;
use App\Models\Master\KasBank;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * JurnalService — Satu-satunya pintu resmi untuk menulis ke jurnal_header,
 * jurnal_detail, dan buku_besar_periode.
 *
 * ATURAN: Tidak ada kode lain yang boleh INSERT langsung ke tabel-tabel
 * tersebut. Semua harus lewat service ini.
 *
 * Tiga cara jurnal bisa dibuat:
 *   1. posting()        — dari template (JTW, BTU, TPI, dll)
 *   2. postingManual()  — baris bebas (KSM, KSK, MTK, penyesuaian)
 *   3. balik()          — membuat jurnal pembalik dari jurnal yang sudah POSTED
 *
 * Satu method tambahan untuk maintenance:
 *   4. bangunBukuBesar() — rebuild ringkasan saldo dari jurnal (idempoten)
 */
class JurnalService
{
    // =========================================================================
    // METHOD 1: posting() — Jurnal dari Template
    // =========================================================================

    /**
     * Posting jurnal otomatis berdasarkan template master_detail_transaksi.
     *
     * Alur kerja:
     *   1. Ambil template baris dari master_detail_transaksi
     *   2. Resolve setiap akun dinamis (KAS_BANK, PERSEDIAAN_UNIT, dll)
     *   3. Hitung nilai tiap baris dari $payload
     *   4. Validasi: total Debit harus = total Kredit
     *   5. Validasi: periode akuntansi harus masih OPEN
     *   6. Generate nomor jurnal
     *   7. CALL sp_post_jurnal (MySQL: INSERT + UPDATE dalam 1 transaksi)
     *   8. Return JurnalHeader yang baru dibuat
     *
     * @param  string  $kodeTransaksi  Kode dari master_transaksi (mis: 'JTW')
     * @param  array   $payload        Nilai variabel yang dibutuhkan template + info akun dinamis
     * @param  string  $sourceType     Nama class model sumber (mis: Penjualan::class)
     * @param  int     $sourceId       ID record sumber
     * @param  string  $keterangan     Deskripsi manusiawi
     *
     * @throws AkunTidakDitemukanException   jika akun dinamis gagal di-resolve
     * @throws JurnalTidakBalanceException   jika Debit ≠ Kredit setelah resolve
     * @throws PeriodeTutupException         jika periode sudah ditutup
     */
    public function posting(
        string $kodeTransaksi,
        array  $payload,
        string $sourceType,
        int    $sourceId,
        string $keterangan = ''
    ): JurnalHeader {
        // --- LANGKAH 1: Ambil template dari database ---
        // MasterDetailTransaksi tidak pakai BelongsToKoperasi karena tabel
        // ini global (tidak per-koperasi) — semua desa pakai template sama.
        $template = MasterDetailTransaksi::where('kode_transaksi', $kodeTransaksi)
            ->orderBy('urutan')
            ->get();

        if ($template->isEmpty()) {
            throw new \InvalidArgumentException(
                "Kode transaksi [{$kodeTransaksi}] tidak ditemukan di master_detail_transaksi."
            );
        }

        // --- LANGKAH 2 & 3: Resolve akun dinamis + hitung nilai ---
        $baris = $this->resolveBarisJurnal($template, $payload);

        // --- LANGKAH 4: Validasi balance Debit = Kredit ---
        $totalDebit  = array_sum(array_column($baris, 'debet'));
        $totalKredit = array_sum(array_column($baris, 'kredit'));

        if (abs($totalDebit - $totalKredit) > 0.001) {
            // Toleransi 0.001 untuk menghindari floating-point imprecision
            throw new JurnalTidakBalanceException($totalDebit, $totalKredit);
        }

        // --- LANGKAH 5: Validasi periode masih OPEN ---
        $tanggal = $payload['tanggal_jurnal'] ?? now()->toDateString();
        [$tahun, $bulan] = $this->parsePeriode($tanggal);
        $this->pastikanPeriodeTerbuka($tahun, $bulan);

        // --- LANGKAH 6: Generate nomor jurnal ---
        $noJurnal = $this->generateNomorJurnal($tahun, $bulan);

        // --- LANGKAH 7: Susun JSON & panggil SP ---
        $jsonPayload = $this->susunJsonPayload([
            'no_jurnal'      => $noJurnal,
            'nomor_nota'     => $payload['nomor_nota'] ?? null,
            'tanggal_jurnal' => $tanggal,
            'periode_tahun'  => $tahun,
            'periode_bulan'  => $bulan,
            'kode_transaksi' => $kodeTransaksi,
            'jenis_jurnal'   => 'OTOMATIS',
            'source_type'    => $sourceType,
            'source_id'      => $sourceId,
            'keterangan'     => $keterangan,
        ], $baris);

        return $this->panggilStoredProcedure($jsonPayload, $sourceType, $sourceId, 'OTOMATIS');
    }

    // =========================================================================
    // METHOD 2: postingManual() — Jurnal Bebas (tanpa template)
    // =========================================================================

    /**
     * Posting jurnal manual — pengguna tentukan sendiri setiap baris.
     * Dipakai untuk: KSM (kas masuk lain), KSK (kas keluar lain),
     * MTK (mutasi antar kas), dan jurnal penyesuaian.
     *
     * @param  array  $header  Data header jurnal
     *                         Wajib: tanggal_jurnal, jenis_jurnal, keterangan
     *                         Opsional: kode_transaksi, nomor_nota
     * @param  array  $baris   Array baris jurnal
     *                         Setiap baris: ['kode_anak', 'posisi' (D/K), 'nilai', 'id_pihak']
     *
     * @throws JurnalTidakBalanceException
     * @throws PeriodeTutupException
     */
    public function postingManual(array $header, array $baris): JurnalHeader
    {
        // Ubah format ['posisi' => 'D', 'nilai' => 100000] menjadi
        // format internal ['debet' => 100000, 'kredit' => 0]
        $barisInternal = array_map(function (array $b, int $idx): array {
            return [
                'urutan'    => $idx + 1,
                'kode_anak' => $b['kode_anak'],
                'debet'     => $b['posisi'] === 'D' ? (float) $b['nilai'] : 0.0,
                'kredit'    => $b['posisi'] === 'K' ? (float) $b['nilai'] : 0.0,
                'id_pihak'  => $b['id_pihak'] ?? null,
                'keterangan'=> $b['keterangan'] ?? null,
            ];
        }, $baris, array_keys($baris));

        // Validasi balance
        $totalDebit  = array_sum(array_column($barisInternal, 'debet'));
        $totalKredit = array_sum(array_column($barisInternal, 'kredit'));

        if (abs($totalDebit - $totalKredit) > 0.001) {
            throw new JurnalTidakBalanceException($totalDebit, $totalKredit);
        }

        // Validasi periode
        $tanggal = $header['tanggal_jurnal'];
        [$tahun, $bulan] = $this->parsePeriode($tanggal);
        $this->pastikanPeriodeTerbuka($tahun, $bulan);

        $noJurnal = $this->generateNomorJurnal($tahun, $bulan);

        $jsonPayload = $this->susunJsonPayload([
            'no_jurnal'      => $noJurnal,
            'nomor_nota'     => $header['nomor_nota'] ?? null,
            'tanggal_jurnal' => $tanggal,
            'periode_tahun'  => $tahun,
            'periode_bulan'  => $bulan,
            'kode_transaksi' => $header['kode_transaksi'] ?? null,
            'jenis_jurnal'   => $header['jenis_jurnal'],  // MANUAL / PENYESUAIAN
            'source_type'    => $header['source_type'] ?? null,
            'source_id'      => $header['source_id'] ?? null,
            'keterangan'     => $header['keterangan'],
        ], $barisInternal);

        return $this->panggilStoredProcedure($jsonPayload, null, null, $header['jenis_jurnal']);
    }

    // =========================================================================
    // METHOD 3: balik() — Jurnal Pembalik
    // =========================================================================

    /**
     * Membuat jurnal pembalik dari jurnal yang sudah POSTED.
     * Setiap baris debet menjadi kredit dan sebaliknya.
     * Jurnal asli ditandai REVERSED.
     *
     * @throws JurnalSudahDibalikException  jika jurnal sudah pernah dibalik
     * @throws \InvalidArgumentException    jika jurnal bukan berstatus POSTED
     */
    public function balik(int $idJurnal, string $alasan): JurnalHeader
    {
        // Ambil jurnal asal beserta detailnya
        // withoutGlobalScope: kita akses by ID langsung, scope sudah dijaga
        // karena id_koperasi ada di WHERE selanjutnya lewat findOrFail
        $jurnalAsal = JurnalHeader::with('detail')->findOrFail($idJurnal);

        // Validasi: belum pernah dibalik
        // Cek apakah ada jurnal pembalik yang menunjuk ke jurnal ini
        $sudahDibalik = JurnalHeader::where('id_jurnal_asal', $idJurnal)->first();
        if ($sudahDibalik) {
            throw new JurnalSudahDibalikException($idJurnal, $sudahDibalik->id_jurnal);
        }

        // Validasi: hanya jurnal POSTED yang bisa dibalik
        if ($jurnalAsal->status !== 'POSTED') {
            throw new \InvalidArgumentException(
                "Jurnal #{$idJurnal} berstatus [{$jurnalAsal->status}], bukan POSTED."
            );
        }

        // Bangun baris pembalik: balik posisi debet ↔ kredit
        $barisPembalik = $jurnalAsal->detail->map(function ($d, int $idx): array {
            return [
                'urutan'    => $idx + 1,
                'kode_anak' => $d->kode_anak,
                'debet'     => (float) $d->kredit,   // ← dibalik
                'kredit'    => (float) $d->debet,    // ← dibalik
                'id_pihak'  => $d->id_pihak,
                'keterangan'=> $d->keterangan,
            ];
        })->toArray();

        // Periode pembalik = hari ini (bukan tanggal jurnal asal)
        $tanggalPembalik = now()->toDateString();
        [$tahun, $bulan] = $this->parsePeriode($tanggalPembalik);
        $this->pastikanPeriodeTerbuka($tahun, $bulan);

        $noJurnal = $this->generateNomorJurnal($tahun, $bulan);

        $jsonPayload = $this->susunJsonPayload([
            'no_jurnal'      => $noJurnal,
            'nomor_nota'     => null,
            'tanggal_jurnal' => $tanggalPembalik,
            'periode_tahun'  => $tahun,
            'periode_bulan'  => $bulan,
            'kode_transaksi' => $jurnalAsal->kode_transaksi,
            'jenis_jurnal'   => 'PEMBALIK',
            'source_type'    => null,
            'source_id'      => null,
            'keterangan'     => "PEMBALIK jurnal #{$idJurnal}: {$alasan}",
        ], $barisPembalik);

        // Posting pembalik via SP (SP sudah memiliki transaksi mandiri)
        $jurnalPembalik = $this->panggilStoredProcedure($jsonPayload, null, null, 'PEMBALIK');

        // Set id_jurnal_asal di jurnal pembalik menunjuk ke jurnal original
        $jurnalPembalik->update(['id_jurnal_asal' => $idJurnal]);

        // Update jurnal asal: REVERSED
        JurnalHeader::where('id_jurnal', $idJurnal)->update([
            'status'         => 'REVERSED',
            'posted_by'      => auth()->id() ?? null,
            'posted_at'      => now(),
        ]);

        return $jurnalPembalik;
    }

    // =========================================================================
    // METHOD 4: bangunBukuBesar() — Rebuild ringkasan saldo (idempoten)
    // =========================================================================

    /**
     * Membangun ulang tabel buku_besar_periode untuk satu bulan tertentu.
     * Aman dipanggil berkali-kali — hasil selalu konsisten dengan data jurnal.
     *
     * Kapan dipakai:
     *   - Saat tutup bulan (TutupBulanService memanggil ini)
     *   - Saat ada jurnal backdate yang masuk belakangan
     *   - Saat data buku besar dicurigai tidak konsisten
     */
    public function bangunBukuBesar(int $tahun, int $bulan): void
    {
        $idKoperasi = app('koperasi_aktif');

        // Hapus ringkasan periode ini — akan dibangun ulang dari nol
        DB::table('buku_besar_periode')
            ->where('id_koperasi', $idKoperasi)
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->delete();

        // Ambil saldo_akhir bulan lalu sebagai saldo_awal bulan ini
        $bulanLalu       = $bulan === 1 ? 12 : $bulan - 1;
        $tahunLalu       = $bulan === 1 ? $tahun - 1 : $tahun;

        $saldoAwalBulanLalu = DB::table('buku_besar_periode')
            ->where('id_koperasi', $idKoperasi)
            ->where('periode_tahun', $tahunLalu)
            ->where('periode_bulan', $bulanLalu)
            ->get()
            ->keyBy('kode_anak');  // index by kode_anak untuk akses cepat

        // Hitung mutasi bulan ini dari jurnal yang sudah POSTED
        $mutasi = DB::table('jurnal_detail as jd')
            ->join('jurnal_header as jh', 'jh.id_jurnal', '=', 'jd.id_jurnal')
            ->where('jh.id_koperasi', $idKoperasi)
            ->where('jh.periode_tahun', $tahun)
            ->where('jh.periode_bulan', $bulan)
            ->where('jh.status', 'POSTED')
            ->selectRaw('jd.kode_anak, SUM(jd.debet) as mutasi_debet, SUM(jd.kredit) as mutasi_kredit')
            ->groupBy('jd.kode_anak')
            ->get();

        // Bangun ulang baris buku_besar_periode
        $rows = $mutasi->map(function ($m) use ($saldoAwalBulanLalu, $idKoperasi, $tahun, $bulan) {
            $saldoAwal = $saldoAwalBulanLalu->get($m->kode_anak);

            $saldoAwalD = $saldoAwal ? (float) $saldoAwal->saldo_akhir_debet  : 0.0;
            $saldoAwalK = $saldoAwal ? (float) $saldoAwal->saldo_akhir_kredit : 0.0;

            return [
                'id_koperasi'       => $idKoperasi,
                'periode_tahun'     => $tahun,
                'periode_bulan'     => $bulan,
                'kode_anak'         => $m->kode_anak,
                'saldo_awal_debet'  => $saldoAwalD,
                'saldo_awal_kredit' => $saldoAwalK,
                'mutasi_debet'      => (float) $m->mutasi_debet,
                'mutasi_kredit'     => (float) $m->mutasi_kredit,
                'saldo_akhir_debet' => $saldoAwalD + (float) $m->mutasi_debet,
                'saldo_akhir_kredit'=> $saldoAwalK + (float) $m->mutasi_kredit,
                'dihitung_pada'     => now(),
            ];
        })->toArray();

        if (!empty($rows)) {
            DB::table('buku_besar_periode')->insert($rows);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS — Logika internal, tidak dipanggil dari luar
    // =========================================================================

    /**
     * Mengubah template baris (dari master_detail_transaksi) menjadi array
     * baris jurnal konkret dengan kode_anak dan nilai yang sudah diisi.
     *
     * Ini adalah inti logika "resolver akun dinamis".
     */
    private function resolveBarisJurnal($template, array $payload): array
    {
        // Cache unit usaha dan kas bank agar tidak query berulang di setiap baris
        $unitUsaha = null;
        $kasBank   = null;

        if (isset($payload['kode_unit'])) {
            $unitUsaha = DB::table('master_unit_usaha')
                ->where('kode_unit_usaha', $payload['kode_unit'])
                ->first();

            if (!$unitUsaha) {
                throw new AkunTidakDitemukanException(
                    'UNIT_USAHA',
                    "Kode unit usaha [{$payload['kode_unit']}] tidak ditemukan"
                );
            }
        }

        if (isset($payload['id_kas_bank'])) {
            $kasBank = KasBank::find($payload['id_kas_bank']);

            if (!$kasBank) {
                throw new AkunTidakDitemukanException(
                    'KAS_BANK',
                    "id_kas_bank [{$payload['id_kas_bank']}] tidak ditemukan"
                );
            }
        }

        $baris = [];

        foreach ($template as $idx => $t) {
            // Tentukan kode akun: dinamis atau sudah tetap?
            $kodeAkun = match ($t->akun_dinamis) {
                'KAS_BANK'        => $this->resolveKasBank($kasBank, $payload),
                'PERSEDIAAN_UNIT' => $this->resolveUnitField($unitUsaha, 'kode_akun_persediaan'),
                'PENDAPATAN_UNIT' => $this->resolvePendapatan($unitUsaha, $payload),
                'HPP_UNIT'        => $this->resolveUnitField($unitUsaha, 'kode_akun_hpp'),
                default           => $t->kode_anak,  // null → kode_anak tetap
            };

            // Ambil nilai dari payload berdasarkan sumber_variabel
            // Contoh: sumber_variabel = 'total_bayar' → $payload['total_bayar']
            $nilai = (float) ($payload[$t->sumber_variabel] ?? 0);

            $baris[] = [
                'urutan'    => $idx + 1,
                'kode_anak' => $kodeAkun,
                'debet'     => $t->posisi === 'D' ? $nilai : 0.0,
                'kredit'    => $t->posisi === 'K' ? $nilai : 0.0,
                'id_pihak'  => $payload['id_pihak'] ?? null,
                'keterangan'=> null,
            ];
        }

        return $baris;
    }

    /** Resolve akun KAS_BANK dari objek KasBank */
    private function resolveKasBank(?object $kasBank, array $payload): string
    {
        if (!$kasBank) {
            throw new AkunTidakDitemukanException(
                'KAS_BANK',
                'id_kas_bank tidak ada di payload'
            );
        }
        return $kasBank->kode_akun;
    }

    /** Resolve akun unit usaha (PERSEDIAAN atau HPP) */
    private function resolveUnitField(?object $unitUsaha, string $field): string
    {
        if (!$unitUsaha) {
            throw new AkunTidakDitemukanException(
                strtoupper($field),
                'kode_unit tidak ada di payload'
            );
        }
        return $unitUsaha->$field;
    }

    /** Resolve PENDAPATAN_UNIT — berbeda berdasarkan status anggota */
    private function resolvePendapatan(?object $unitUsaha, array $payload): string
    {
        if (!$unitUsaha) {
            throw new AkunTidakDitemukanException(
                'PENDAPATAN_UNIT',
                'kode_unit tidak ada di payload'
            );
        }

        // is_anggota harus ada di payload — Controller yang menentukannya
        // berdasarkan data master_pihak (apakah pembeli adalah anggota koperasi)
        $isAnggota = $payload['is_anggota'] ?? false;

        return $isAnggota
            ? $unitUsaha->kode_akun_pendapatan_anggota
            : $unitUsaha->kode_akun_pendapatan_non_anggota;
    }

    /** Validasi bahwa periode akuntansi masih OPEN */
    private function pastikanPeriodeTerbuka(int $tahun, int $bulan): void
    {
        $status = DB::table('periode_akuntansi')
            ->where('id_koperasi', app('koperasi_aktif'))
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->value('status');

        if ($status === null) {
            // Periode belum dibuat — anggap terbuka (sistem baru / fresh)
            return;
        }

        if ($status !== 'OPEN') {
            throw new PeriodeTutupException($tahun, $bulan);
        }
    }

    /** Ubah string tanggal menjadi [tahun, bulan] */
    private function parsePeriode(string $tanggal): array
    {
        $dt = \Carbon\Carbon::parse($tanggal);
        return [$dt->year, $dt->month];
    }

    /**
     * Generate nomor jurnal unik untuk periode ini.
     * Format: JRN-{tahun}-{bulan_2digit}-{sequence_4digit}
     * Contoh: JRN-2026-08-0042
     */
    private function generateNomorJurnal(int $tahun, int $bulan): string
    {
        $prefix = sprintf('JRN-%d-%02d-', $tahun, $bulan);

        // Ambil nomor urut terakhir untuk periode ini dari koperasi aktif
        $terakhir = DB::table('jurnal_header')
            ->where('id_koperasi', app('koperasi_aktif'))
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->where('no_jurnal', 'like', $prefix . '%')
            ->lockForUpdate()   // ← penting: mencegah race condition
            ->count();

        return $prefix . sprintf('%04d', $terakhir + 1);
    }

    /** Gabungkan header dan baris menjadi JSON string untuk dikirim ke SP */
    private function susunJsonPayload(array $header, array $baris): string
    {
        return json_encode([...$header, 'baris' => $baris], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Memanggil Stored Procedure sp_post_jurnal dan mengembalikan JurnalHeader.
     *
     * Menangani dua kasus:
     *   a. INSERT baru berhasil → kembalikan jurnal yang baru dibuat
     *   b. UniqueConstraintViolation pada jurnal_idempoten →
     *      berarti jurnal sumber ini sudah pernah diposting.
     *      Kembalikan jurnal yang sudah ada (idempoten — aman diulang).
     */
    private function panggilStoredProcedure(
        string  $jsonPayload,
        ?string $sourceType,
        ?int    $sourceId,
        string  $jenisJurnal
    ): JurnalHeader {
        try {
            // CALL SP — mengembalikan satu baris dengan kolom id_jurnal
            $hasil = DB::select('CALL sp_post_jurnal(?, ?, ?)', [
                app('koperasi_aktif'),
                Auth::id(),
                $jsonPayload,
            ]);

            $idJurnal = $hasil[0]->id_jurnal;

            // Ambil JurnalHeader yang baru dibuat
            return JurnalHeader::findOrFail($idJurnal);

        } catch (UniqueConstraintViolationException $e) {
            // Idempotency guard: jurnal untuk source ini sudah ada.
            // Kembalikan yang sudah ada tanpa error — aman untuk retry.
            if ($sourceType && $sourceId) {
                $existing = JurnalHeader::where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('jenis_jurnal', $jenisJurnal)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // Jika bukan idempotency (no_jurnal duplikat, dsb) → lempar ulang
            throw $e;
        }
    }
}
