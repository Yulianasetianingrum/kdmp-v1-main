<?php

namespace App\Http\Controllers\Akuntansi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\AsetTetap;

class AsetTetapController extends ModuleCrudController
{
    protected string $model = AsetTetap::class;
    protected string $view = 'akuntansi.aset-tetap';
    protected string $title = 'Aset Tetap';
    protected string $routeBase = 'akuntansi.aset-tetap';
}
