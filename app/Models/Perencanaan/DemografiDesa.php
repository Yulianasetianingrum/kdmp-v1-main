<?php

namespace App\Models\Perencanaan;

use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

/** Populasi disimpan SATU KALI di sini, bukan diulang per komoditas. */
class DemografiDesa extends Model
{
    protected $table = 'demografi_desa';
    protected $primaryKey = 'id_demografi';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_wilayah', 'id_sesi_survei', 'tahun', 'kelompok_umur', 'jumlah_penduduk',
    ];

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }
}
