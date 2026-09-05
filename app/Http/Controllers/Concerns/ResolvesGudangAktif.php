<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Satu gudang per desa (README). Modul Gudang di Fase 2 mengasumsikan
 * pengguna yang login adalah pengguna desa (id_koperasi terisi) — pengguna
 * pusat (id_koperasi NULL, mode konsolidasi) belum didukung di sini.
 */
trait ResolvesGudangAktif
{
    private function gudangAktif(): object
    {
        $koperasiId = auth()->user()->id_koperasi
            ?? throw new HttpException(403, 'Modul Gudang hanya untuk pengguna desa, bukan pengguna pusat.');

        $gudang = DB::table('gudang')->where('id_koperasi', $koperasiId)->first();

        if (! $gudang) {
            throw new HttpException(404, 'Koperasi ini belum punya gudang.');
        }

        return $gudang;
    }
}
