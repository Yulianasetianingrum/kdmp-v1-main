<?php

namespace App\Http\Controllers\Survei;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Survei\SesiSurvei;

class SesiSurveiController extends ModuleCrudController
{
    protected string $model = SesiSurvei::class;
    protected string $view = 'survei.sesi';
    protected string $title = 'Sesi Survei';
    protected string $routeBase = 'survei.sesi';
    protected array $withRelations = ['wilayah', 'petugas'];
}
