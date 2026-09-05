<?php

namespace App\Models\Perencanaan;

use App\Models\Master\Komoditas;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

/** Produksi per bulan panen. Padi tidak panen 12 kali setahun. */
class KetersediaanKomoditas extends Model
{
    protected $table = 'ketersediaan_komoditas';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_wilayah', 'id_komoditas', 'id_sesi_survei', 'tahun', 'bulan',
        'jumlah_produksi', 'satuan',
    ];

    protected function casts(): array
    {
        return ['jumlah_produksi' => 'decimal:4'];
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas');
    }
}
