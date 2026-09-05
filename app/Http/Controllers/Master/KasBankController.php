<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\KasBank;

class KasBankController extends ModuleCrudController
{
    protected string $model = KasBank::class;
    protected string $view = 'master.kas-bank';
    protected string $title = 'Kas & Bank';
    protected string $routeBase = 'master.kas-bank';
}
