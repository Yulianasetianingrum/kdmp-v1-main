<?php

namespace App\Http\Controllers\Akuntansi;

use App\Http\Controllers\Controller;
use App\Services\Finance\TutupBulanService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TutupBulanController — mengelola halaman konfirmasi dan eksekusi tutup bulan.
 *
 * Alur pengguna:
 *   1. GET  /akuntansi/tutup-bulan           → pilih bulan/tahun, lihat 8 validasi
 *   2. POST /akuntansi/tutup-bulan/validasi  → refresh status validasi (AJAX-friendly)
 *   3. POST /akuntansi/tutup-bulan           → eksekusi tutup bulan jika semua lulus
 */
class TutupBulanController extends Controller
{
    public function __construct(private TutupBulanService $service) {}

    /**
     * GET — Tampilkan form pilih periode + status 8 validasi.
     */
    public function index(Request $request): View
    {
        // Default: bulan lalu (biasanya yang ingin ditutup)
        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->subMonth()->month);
        // Sesuaikan tahun jika bulan = 12 dan saat ini sudah Januari
        if ($bulan === 12 && now()->month === 1) {
            $tahun = now()->subYear()->year;
        }

        $validasi = $this->service->cekValidasi($tahun, $bulan);
        $semuaLulus = collect($validasi)->every(fn($v) => $v['lulus']);

        return view('akuntansi.tutup-bulan', compact('tahun', 'bulan', 'validasi', 'semuaLulus'));
    }

    /**
     * POST — Eksekusi tutup bulan setelah pengguna konfirmasi.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2099'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $tahun = (int) $request->input('tahun');
        $bulan = (int) $request->input('bulan');

        try {
            $periode = $this->service->tutupBulan($tahun, $bulan);

            $namaBulan = Carbon::createFromDate($tahun, $bulan, 1)
                ->translatedFormat('F Y');

            return redirect()
                ->route('akuntansi.tutup-bulan.index', ['tahun' => $tahun, 'bulan' => $bulan])
                ->with('success', "✅ Periode {$namaBulan} berhasil ditutup.");

        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
