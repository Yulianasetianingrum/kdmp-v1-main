<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Tenant\KoperasiDesa;

class KoperasiController extends ModuleCrudController
{
    protected string $model = KoperasiDesa::class;
    protected string $view = 'master.koperasi';
    protected string $title = 'Koperasi Desa';
    protected string $routeBase = 'master.koperasi';
}
