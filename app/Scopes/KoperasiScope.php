<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolasi tenant di level baris.
 *
 * Tenant aktif diambil dari container binding 'koperasi_aktif' yang diisi
 * middleware SetKoperasiAktif. Kalau tidak ada tenant aktif (mis. artisan
 * command atau pengguna tingkat pusat), scope tidak dipasang.
 *
 * JANGAN gunakan withoutGlobalScope di luar modul pelaporan konsolidasi.
 */
class KoperasiScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app()->bound('koperasi_aktif') ? app('koperasi_aktif') : null;

        if ($id !== null) {
            $builder->where($model->getTable().'.id_koperasi', $id);
        }
    }
}
