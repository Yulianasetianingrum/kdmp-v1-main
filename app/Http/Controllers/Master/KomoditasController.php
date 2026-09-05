<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\Komoditas;

class KomoditasController extends ModuleCrudController
{
    protected string $model = Komoditas::class;
    protected string $view = 'master.komoditas';
    protected string $title = 'Komoditas';
    protected string $routeBase = 'master.komoditas';
}
