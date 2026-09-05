<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kerangka controller CRUD generik untuk modul yang belum didalami.
 *
 * Setiap controller modul cukup mengisi properti $model, $view, $title,
 * $routeBase, dan (opsional) $withRelations. index() sudah menampilkan data
 * SUNGGUHAN dari database (bukan dummy) supaya kerangka ini langsung bisa
 * dicoba begitu migrate+seed dijalankan. create/store/edit/update/destroy
 * SENGAJA masih placeholder — logika bisnis (validasi, service, jurnal)
 * disusun saat pendalaman tiap modul, bukan bagian dari kerangka ini.
 *
 * Controller modul yang butuh alur berbeda (mis. Finance dengan posting
 * jurnal) tinggal override method yang relevan atau tidak extend kelas ini
 * sama sekali.
 */
abstract class ModuleCrudController extends Controller
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $model;

    protected string $view;

    protected string $title;

    protected string $routeBase;

    /** @var string[] */
    protected array $withRelations = [];

    protected int $perPage = 15;

    public function index(Request $request): View
    {
        $query = ($this->model)::query();

        foreach ($this->withRelations as $relation) {
            $query->with($relation);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableColumns() as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        $items = $query->latest($query->getModel()->getKeyName())->paginate($this->perPage)->withQueryString();

        return view('modules.index', [
            'items' => $items,
            'title' => $this->title,
            'routeBase' => $this->routeBase,
            'columns' => $this->displayColumns(),
        ]);
    }

    public function show(int|string $id): View
    {
        $item = ($this->model)::query();

        foreach ($this->withRelations as $relation) {
            $item->with($relation);
        }

        return view('modules.placeholder', [
            'title' => $this->title,
            'routeBase' => $this->routeBase,
            'mode' => 'show',
            'item' => $item->findOrFail($id),
        ]);
    }

    public function create(): View
    {
        return view('modules.placeholder', [
            'title' => 'Tambah '.$this->title,
            'routeBase' => $this->routeBase,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return back()->with('info', 'Form '.$this->title.' belum diimplementasikan di kerangka ini — menyusul saat pendalaman modul.');
    }

    public function edit(int|string $id): View
    {
        $item = ($this->model)::findOrFail($id);

        return view('modules.placeholder', [
            'title' => 'Ubah '.$this->title,
            'routeBase' => $this->routeBase,
            'mode' => 'edit',
            'item' => $item,
        ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        return back()->with('info', 'Form '.$this->title.' belum diimplementasikan di kerangka ini — menyusul saat pendalaman modul.');
    }

    public function destroy(int|string $id): RedirectResponse
    {
        return back()->with('info', 'Aksi hapus belum diimplementasikan di kerangka ini.');
    }

    /** Kolom yang ditampilkan di tabel index — default: semua fillable. */
    protected function displayColumns(): array
    {
        $instance = new ($this->model);

        return array_slice($instance->getFillable(), 0, 6);
    }

    /** Kolom yang dipakai kotak pencarian sederhana. */
    protected function searchableColumns(): array
    {
        return array_slice($this->displayColumns(), 0, 2);
    }
}
