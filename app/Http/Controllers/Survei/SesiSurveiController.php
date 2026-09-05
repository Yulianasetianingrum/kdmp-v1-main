<?php

namespace App\Http\Controllers\Survei;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Survei\SesiSurvei;
use App\Models\Tenant\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SesiSurveiController extends ModuleCrudController
{
    protected string $model = SesiSurvei::class;
    protected string $view = 'survei.sesi';
    protected string $title = 'Sesi Survei';
    protected string $routeBase = 'survei.sesi';
    protected array $withRelations = ['wilayah', 'petugas'];

    public function create(): View
    {
        $wilayahs = Wilayah::all();
        $bulans = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return view('survei.sesi.create', [
            'title' => 'Buat Sesi Survei Baru',
            'wilayahs' => $wilayahs,
            'bulans' => $bulans,
            'routeBase' => $this->routeBase
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_wilayah' => 'required|exists:wilayah,id',
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
            'tanggal_survei' => 'required|date',
        ]);

        $validated['status'] = 'DRAFT';
        $validated['token_publik'] = Str::random(32);
        $validated['id_petugas'] = auth()->id();

        $sesi = SesiSurvei::create($validated);

        return redirect()->route('survei.sesi.show', $sesi->id)
            ->with('success', 'Sesi survei berhasil dibuat. Silakan bagikan tautan ke petugas lapangan.');
    }
    
    public function show(int|string $id): View
    {
        $item = SesiSurvei::with(['wilayah', 'petugas'])->findOrFail($id);
        
        return view('survei.sesi.show', [
            'title' => 'Detail Sesi Survei',
            'item' => $item,
            'routeBase' => $this->routeBase
        ]);
    }

    public function edit(int|string $id): View
    {
        $item = SesiSurvei::findOrFail($id);
        
        $wilayahs = Wilayah::all();
        $bulans = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return view('survei.sesi.edit', [
            'title' => 'Ubah Sesi Survei',
            'item' => $item,
            'wilayahs' => $wilayahs,
            'bulans' => $bulans,
            'routeBase' => $this->routeBase
        ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $sesi = SesiSurvei::findOrFail($id);

        $validated = $request->validate([
            'id_wilayah' => 'required|exists:wilayah,id',
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
            'tanggal_survei' => 'required|date',
        ]);

        $sesi->update($validated);

        return redirect()->route('survei.sesi.show', $sesi->id)
            ->with('success', 'Sesi survei berhasil diperbarui.');
    }

    public function destroy(int|string $id): RedirectResponse
    {
        $sesi = SesiSurvei::findOrFail($id);
        $sesi->delete();

        return back()->with('success', 'Sesi survei berhasil dihapus.');
    }
}
