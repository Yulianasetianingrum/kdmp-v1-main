<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kontrol buka/tutup periode.
 *
 * Bulan 13 adalah periode penyesuaian. Tahun N bulan 13 boleh tetap OPEN
 * sampai Maret tahun N+1 sementara tahun N+1 bulan 1-3 sudah berjalan.
 *
 * Periode ditentukan dari TANGGAL JURNAL, bukan tanggal input.
 */
class PeriodeService
{
    public function pastikanTerbuka(int $koperasiId, string $tanggal): void
    {
        $c = Carbon::parse($tanggal);

        $status = DB::table('periode_akuntansi')
            ->where('id_koperasi', $koperasiId)
            ->where('tahun', $c->year)
            ->where('bulan', $c->month)
            ->value('status');

        if ($status === null) {
            throw new RuntimeException(
                "Periode {$c->month}/{$c->year} belum dibuat untuk koperasi ini."
            );
        }

        if ($status !== 'OPEN') {
            throw new RuntimeException(
                "Periode {$c->month}/{$c->year} berstatus {$status}. Tidak bisa diposting."
            );
        }
    }

    /** Buat 13 periode sekaligus untuk satu tahun buku. */
    public function bukaTahun(int $koperasiId, int $tahun): void
    {
        for ($bulan = 1; $bulan <= 13; $bulan++) {
            DB::table('periode_akuntansi')->updateOrInsert(
                ['id_koperasi' => $koperasiId, 'tahun' => $tahun, 'bulan' => $bulan],
                ['status' => 'OPEN']
            );
        }
    }

    public function tutup(int $koperasiId, int $tahun, int $bulan, int $userId): void
    {
        DB::table('periode_akuntansi')
            ->where('id_koperasi', $koperasiId)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->update([
                'status'       => 'CLOSED',
                'tgl_tutup'    => now(),
                'ditutup_oleh' => $userId,
            ]);
    }
}
