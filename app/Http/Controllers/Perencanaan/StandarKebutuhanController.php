<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\StandarKebutuhanKomoditas;

class StandarKebutuhanController extends ModuleCrudController
{
    protected string $model = StandarKebutuhanKomoditas::class;
    protected string $view = 'perencanaan.standar-kebutuhan';
    protected string $title = 'Standar Kebutuhan Komoditas';
    protected string $routeBase = 'perencanaan.standar-kebutuhan';
    protected array $withRelations = ['komoditas'];
}
