<?php

namespace App\Http\Controllers\Akuntansi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Akuntansi\ConfigShu;

class ConfigShuController extends ModuleCrudController
{
    protected string $model = ConfigShu::class;
    protected string $view = 'akuntansi.config-shu';
    protected string $title = 'Konfigurasi Pembagian SHU';
    protected string $routeBase = 'akuntansi.config-shu';
}
