<?php

namespace App\Http\Controllers\Akuntansi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Akuntansi\JurnalHeader;

/**
 * Read-only sesuai desain: satu-satunya jalan MENULIS jurnal adalah
 * JurnalService::posting()/postingManual() (dibangun besok), bukan form
 * CRUD biasa. create/store/edit/update/destroy di kerangka dasar sengaja
 * tidak dipakai di sini.
 */
class JurnalController extends ModuleCrudController
{
    protected string $model = JurnalHeader::class;
    protected string $view = 'akuntansi.jurnal';
    protected string $title = 'Jurnal';
    protected string $routeBase = 'akuntansi.jurnal';
}
