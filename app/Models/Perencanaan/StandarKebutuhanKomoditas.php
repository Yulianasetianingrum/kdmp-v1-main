<?php

namespace App\Models\Perencanaan;

use App\Models\Master\Komoditas;
use Illuminate\Database\Eloquent\Model;

/** Koefisien berversi: revisi tidak boleh mengubah hasil kalkulasi bulan lalu. */
class StandarKebutuhanKomoditas extends Model
{
    protected $table = 'standar_kebutuhan_komoditas';
    const UPDATED_AT = null;

    protected $fillable = [
        'sektor', 'id_komoditas', 'kelompok_umur', 'per_kapita_harian', 'satuan',
        'sumber', 'berlaku_mulai', 'berlaku_sampai',
    ];

    protected function casts(): array
    {
        return [
            'per_kapita_harian' => 'decimal:6',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas');
    }
}
