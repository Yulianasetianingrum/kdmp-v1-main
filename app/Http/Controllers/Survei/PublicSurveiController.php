<?php

namespace App\Http\Controllers\Survei;

use App\Http\Controllers\Controller;
use App\Models\Survei\SesiSurvei;
use App\Models\Survei\Pertanyaan;
use App\Models\Survei\Jawaban;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PublicSurveiController extends Controller
{
    public function show(string $token)
    {
        $sesi = SesiSurvei::where('token_publik', $token)
            ->with('wilayah')
            ->firstOrFail();

        // Ambil semua pertanyaan yang aktif.
        $pertanyaan = Pertanyaan::where('is_active', true)->orderBy('urutan')->get();

        return view('survei.public.isi', [
            'sesi' => $sesi,
            'pertanyaans' => $pertanyaan
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $sesi = SesiSurvei::where('token_publik', $token)->firstOrFail();

        $data = $request->validate([
            'jawaban' => 'required|array',
            'jawaban.*' => 'nullable'
        ]);

        foreach ($data['jawaban'] ?? [] as $id_pertanyaan => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $pertanyaan = Pertanyaan::find($id_pertanyaan);
            if (!$pertanyaan) continue;

            $jawaban = new Jawaban();
            $jawaban->id_sesi = $sesi->id;
            $jawaban->id_pertanyaan = $id_pertanyaan;
            
            // Asumsi sederhana: cek tipe_jawaban
            if ($pertanyaan->tipe_jawaban === 'ANGKA' || $pertanyaan->tipe_jawaban === 'angka') {
                $jawaban->nilai_angka = (float) $nilai;
            } else {
                $jawaban->nilai_teks = (string) $nilai;
            }

            $jawaban->save();
        }

        // Ubah status ke TERISI atau biarkan DRAFT (Tergantung workflow, kita biarkan dulu atau set ke TERISI jika ada)
        // $sesi->update(['status' => 'TERISI']);

        return redirect()->route('survei.public.show', $token)
            ->with('success', 'Terima kasih, data survei berhasil dikirim.');
    }
}
