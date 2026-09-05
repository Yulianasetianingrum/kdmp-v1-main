<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\Pihak;

class PihakController extends ModuleCrudController
{
    protected string $model = Pihak::class;
    protected string $view = 'master.pihak';
    protected string $title = 'Pihak (Supplier / Petani / Warga / Mitra Desa)';
    protected string $routeBase = 'master.pihak';
}
