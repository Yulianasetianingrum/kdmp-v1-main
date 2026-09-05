<?php

namespace App\Models\Perencanaan;

use App\Models\Master\Komoditas;
use App\Models\Master\UnitUsaha;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

class HasilKalkulasi extends Model
{
    protected $table = 'hasil_kalkulasi';
    protected $primaryKey = 'id_hasil';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_wilayah', 'id_komoditas', 'tahun', 'bulan', 'total_kebutuhan',
        'total_ketersediaan', 'selisih', 'status_surplus_defisit',
        'persentase_kecukupan', 'id_unit_usaha_rekomendasi', 'alasan_rekomendasi',
        'prioritas', 'versi', 'status',
    ];

    protected function casts(): array
    {
        return [
            'total_kebutuhan' => 'decimal:4',
            'total_ketersediaan' => 'decimal:4',
            'selisih' => 'decimal:4',
            'persentase_kecukupan' => 'decimal:2',
        ];
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas');
    }

    public function unitUsahaRekomendasi()
    {
        return $this->belongsTo(UnitUsaha::class, 'id_unit_usaha_rekomendasi', 'id_unit_usaha');
    }
}
