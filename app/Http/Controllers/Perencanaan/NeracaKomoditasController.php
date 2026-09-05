<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\HasilKalkulasi;

class NeracaKomoditasController extends ModuleCrudController
{
    protected string $model = HasilKalkulasi::class;
    protected string $view = 'perencanaan.neraca-komoditas';
    protected string $title = 'Neraca Komoditas (Surplus / Defisit)';
    protected string $routeBase = 'perencanaan.neraca-komoditas';
    protected array $withRelations = ['wilayah', 'komoditas'];
}
