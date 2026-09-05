<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Tenant\PeriodeAkuntansi;

class PeriodeController extends ModuleCrudController
{
    protected string $model = PeriodeAkuntansi::class;
    protected string $view = 'master.periode';
    protected string $title = 'Periode Akuntansi';
    protected string $routeBase = 'master.periode';
}
