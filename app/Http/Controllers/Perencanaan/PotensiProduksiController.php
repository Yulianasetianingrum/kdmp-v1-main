<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\KetersediaanKomoditas;

class PotensiProduksiController extends ModuleCrudController
{
    protected string $model = KetersediaanKomoditas::class;
    protected string $view = 'perencanaan.potensi-produksi';
    protected string $title = 'Potensi Produksi';
    protected string $routeBase = 'perencanaan.potensi-produksi';
    protected array $withRelations = ['wilayah', 'komoditas'];
}
