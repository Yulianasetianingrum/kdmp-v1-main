<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi container binding 'koperasi_aktif' yang dibaca KoperasiScope.
 *
 * Pengguna desa (id_koperasi terisi) selalu terkunci ke desanya sendiri.
 * Pengguna pusat (id_koperasi NULL) memilih tenant lewat session; kalau
 * belum memilih, tidak ada binding sama sekali sehingga KoperasiScope
 * tidak memfilter apa pun (mode konsolidasi, hanya untuk pelaporan).
 */
class SetKoperasiAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna !== null) {
            $id = $pengguna->id_koperasi ?? $request->session()->get('koperasi_aktif_pilihan');

            if ($id !== null) {
                app()->instance('koperasi_aktif', $id);
            }
        }

        return $next($request);
    }
}
