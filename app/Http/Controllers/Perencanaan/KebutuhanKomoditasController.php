<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\KebutuhanKomoditas;

class KebutuhanKomoditasController extends ModuleCrudController
{
    protected string $model = KebutuhanKomoditas::class;
    protected string $view = 'perencanaan.kebutuhan-komoditas';
    protected string $title = 'Kebutuhan Komoditas (Hasil Kalkulasi M2)';
    protected string $routeBase = 'perencanaan.kebutuhan-komoditas';
    protected array $withRelations = ['wilayah', 'komoditas'];
}
