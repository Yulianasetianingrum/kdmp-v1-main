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
                // Bersihkan format angka Indonesia (hapus titik pemisah ribuan, ganti koma jadi titik desimal)
                $cleanNilai = preg_replace('/[^0-9.,-]/', '', (string) $nilai);
                
                // Jika mengandung koma (sebagai desimal) dan titik (sebagai ribuan)
                // Contoh: 25.000,50 -> 25000.50
                $cleanNilai = str_replace('.', '', $cleanNilai);
                $cleanNilai = str_replace(',', '.', $cleanNilai);
                
                $jawaban->nilai_angka = (float) $cleanNilai;
            } else {
                $jawaban->nilai_teks = (string) $nilai;
            }

            $jawaban->save();
        }

        // Ubah status ke TERKIRIM
        $sesi->update(['status' => 'terkirim']);

        return redirect()->route('survei.public.show', $token)
            ->with('success', 'Terima kasih, data survei berhasil dikirim.');
    }
}
