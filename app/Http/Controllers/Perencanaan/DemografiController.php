<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\DemografiDesa;

class DemografiController extends ModuleCrudController
{
    protected string $model = DemografiDesa::class;
    protected string $view = 'perencanaan.demografi';
    protected string $title = 'Demografi Desa';
    protected string $routeBase = 'perencanaan.demografi';
    protected array $withRelations = ['wilayah'];
}
