<?php

namespace App\Models\Concerns;

use App\Scopes\KoperasiScope;

trait BelongsToKoperasi
{
    public static function bootBelongsToKoperasi(): void
    {
        static::addGlobalScope(new KoperasiScope);

        // Isi id_koperasi otomatis saat membuat record baru
        static::creating(function ($model) {
            if (empty($model->id_koperasi) && app()->bound('koperasi_aktif')) {
                $model->id_koperasi = app('koperasi_aktif');
            }
        });
    }
}
