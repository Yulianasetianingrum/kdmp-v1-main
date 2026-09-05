<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\Satuan;

class SatuanController extends ModuleCrudController
{
    protected string $model = Satuan::class;
    protected string $view = 'master.satuan';
    protected string $title = 'Satuan';
    protected string $routeBase = 'master.satuan';
}
