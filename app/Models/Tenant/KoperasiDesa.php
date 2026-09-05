<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class KoperasiDesa extends Model
{
    protected $table = 'koperasi_desa';
    protected $primaryKey = 'id_koperasi';

    protected $fillable = [
        'kode_koperasi', 'nama_koperasi', 'id_wilayah', 'badan_hukum_no',
        'tgl_berdiri', 'tahun_buku_awal', 'is_active',
    ];

    protected function casts(): array
    {
        return ['tgl_berdiri' => 'date', 'is_active' => 'boolean'];
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function periodeAkuntansi()
    {
        return $this->hasMany(PeriodeAkuntansi::class, 'id_koperasi', 'id_koperasi');
    }

    public function pengguna()
    {
        return $this->hasMany(\App\Models\Pengguna::class, 'id_koperasi', 'id_koperasi');
    }
}
