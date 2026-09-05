<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Akuntansi\Coa;

class CoaController extends ModuleCrudController
{
    protected string $model = Coa::class;
    protected string $view = 'master.coa';
    protected string $title = 'Chart of Accounts';
    protected string $routeBase = 'master.coa';
    protected int $perPage = 30;
}
