<?php

namespace App\Models\Perencanaan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Komoditas;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

class PerbandinganHarga extends Model
{
    use BelongsToKoperasi;

    protected $table = 'perbandingan_harga';
    protected $primaryKey = 'id_perbandingan';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_komoditas', 'id_wilayah_sumber', 'bulan', 'tahun',
        'harga_ditawarkan', 'jumlah_tersedia', 'jarak_ke_gudang', 'estimasi_ongkir',
        'harga_efektif', 'rank_harga', 'dipilih',
    ];

    protected function casts(): array
    {
        return [
            'harga_ditawarkan' => 'decimal:2',
            'jumlah_tersedia' => 'decimal:4',
            'harga_efektif' => 'decimal:2',
            'dipilih' => 'boolean',
        ];
    }

    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas');
    }

    public function wilayahSumber()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_sumber');
    }
}
