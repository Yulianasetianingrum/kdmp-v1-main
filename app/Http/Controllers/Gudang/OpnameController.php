<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Gudang\OpnameHeader;

class OpnameController extends ModuleCrudController
{
    protected string $model = OpnameHeader::class;
    protected string $view = 'gudang.opname';
    protected string $title = 'Stock Opname';
    protected string $routeBase = 'gudang.opname';
}
