<?php

namespace App\Models\Perencanaan;

use App\Models\Master\Komoditas;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

/** Hasil kalkulasi disimpan sebagai SNAPSHOT, bukan dihitung ulang tiap dibuka. */
class KebutuhanKomoditas extends Model
{
    protected $table = 'kebutuhan_komoditas';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_wilayah', 'id_komoditas', 'tahun', 'bulan', 'kelompok_umur',
        'jumlah_penduduk', 'per_kapita_harian', 'faktor_musiman',
        'kebutuhan_bulanan', 'satuan', 'id_standar',
    ];

    protected function casts(): array
    {
        return [
            'per_kapita_harian' => 'decimal:6',
            'faktor_musiman' => 'decimal:4',
            'kebutuhan_bulanan' => 'decimal:4',
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
}
