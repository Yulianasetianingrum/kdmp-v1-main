<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * koperasi_desa.id_wilayah unik (satu desa = satu koperasi) — dipakai modul
 * Perencanaan (Fase 7) untuk mengaitkan demografi/kebutuhan/neraca ke
 * wilayah desa milik pengguna yang login.
 */
trait ResolvesWilayahAktif
{
    private function wilayahAktifId(): int
    {
        $koperasiId = auth()->user()->id_koperasi
            ?? throw new HttpException(403, 'Modul ini hanya untuk pengguna desa, bukan pengguna pusat.');

        $idWilayah = DB::table('koperasi_desa')->where('id_koperasi', $koperasiId)->value('id_wilayah');

        if (! $idWilayah) {
            throw new HttpException(404, 'Koperasi ini belum terhubung ke wilayah.');
        }

        return $idWilayah;
    }
}
