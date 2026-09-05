<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen per koperasi per bulan.
 * Format: PREFIX/KODEKOP/YYMM/00001
 *
 * Memakai kunci baris agar dua kasir yang menekan simpan bersamaan
 * tidak mendapat nomor yang sama.
 */
class NomorDokumen
{
    public function berikutnya(int $koperasiId, string $prefix, ?Carbon $tanggal = null): string
    {
        $tanggal ??= now();
        $periode = $tanggal->format('ym');
        $kunci   = "nomor:{$prefix}:{$periode}";

        return DB::transaction(function () use ($koperasiId, $prefix, $periode, $kunci) {
            $row = DB::table('konfigurasi')
                ->where('id_koperasi', $koperasiId)
                ->where('kunci', $kunci)
                ->lockForUpdate()
                ->first();

            $urut = $row ? ((int) $row->nilai) + 1 : 1;

            DB::table('konfigurasi')->updateOrInsert(
                ['id_koperasi' => $koperasiId, 'kunci' => $kunci],
                ['nilai' => (string) $urut, 'keterangan' => 'counter nomor dokumen']
            );

            $kodeKop = DB::table('koperasi_desa')->where('id_koperasi', $koperasiId)->value('kode_koperasi');

            return sprintf('%s/%s/%s/%05d', $prefix, $kodeKop, $periode, $urut);
        });
    }
}
