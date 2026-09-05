<?php

namespace App\Models\Master;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

class Pihak extends Model
{
    use BelongsToKoperasi;

    protected $table = 'master_pihak';
    protected $primaryKey = 'id_pihak';

    protected $fillable = [
        'id_koperasi', 'jenis_pihak', 'id_koperasi_mitra', 'nama', 'nik',
        'is_anggota', 'no_anggota', 'tgl_jadi_anggota', 'alamat', 'no_hp',
        'id_wilayah', 'kualitas_rating', 'estimasi_pengiriman', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_anggota' => 'boolean',
            'is_active' => 'boolean',
            'tgl_jadi_anggota' => 'date',
        ];
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }
}
