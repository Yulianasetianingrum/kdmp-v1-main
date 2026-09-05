<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Baca dari view SQL v_rekonsiliasi_konsinyasi (dibuat di migrasi jurnal) —
 * hanya menampilkan baris di mana piutang pemilik != hutang penerima.
 * Idealnya daftar ini SELALU kosong. Lihat validasi tutup bulan #8.
 */
class RekonsiliasiController extends Controller
{
    public function index(): View
    {
        $selisih = DB::table('v_rekonsiliasi_konsinyasi')->get();

        return view('konsinyasi.rekonsiliasi', ['selisih' => $selisih]);
    }
}
