<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\Gudang;

class GudangController extends ModuleCrudController
{
    protected string $model = Gudang::class;
    protected string $view = 'master.gudang';
    protected string $title = 'Gudang';
    protected string $routeBase = 'master.gudang';
}
