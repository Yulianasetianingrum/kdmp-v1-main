<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class PeriodeAkuntansi extends Model
{
    protected $table = 'periode_akuntansi';
    protected $primaryKey = 'id_periode';
    public $timestamps = false;

    protected $fillable = ['id_koperasi', 'tahun', 'bulan', 'status', 'tgl_tutup', 'ditutup_oleh'];

    protected function casts(): array
    {
        return ['tgl_tutup' => 'datetime'];
    }

    public function koperasi()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi', 'id_koperasi');
    }
}
