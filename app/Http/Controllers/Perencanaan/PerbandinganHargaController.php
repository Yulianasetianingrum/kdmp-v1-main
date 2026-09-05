<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\PerbandinganHarga;

class PerbandinganHargaController extends ModuleCrudController
{
    protected string $model = PerbandinganHarga::class;
    protected string $view = 'perencanaan.perbandingan-harga';
    protected string $title = 'Perbandingan Harga';
    protected string $routeBase = 'perencanaan.perbandingan-harga';
    protected array $withRelations = ['komoditas', 'wilayahSumber'];
}
