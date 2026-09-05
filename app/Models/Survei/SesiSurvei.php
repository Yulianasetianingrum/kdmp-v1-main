<?php

namespace App\Models\Survei;

use App\Models\Pengguna;
use App\Models\Tenant\Wilayah;
use Illuminate\Database\Eloquent\Model;

/** token_publik: URL dibagikan ke pengurus desa untuk pengisian via suara. */
class SesiSurvei extends Model
{
    protected $table = 'sesi_survei';

    protected $fillable = [
        'id_petugas', 'id_wilayah', 'tahun', 'bulan', 'tanggal_survei', 'status',
        'catatan', 'id_perangkat', 'uuid_sesi_klien', 'token_publik', 'token_kadaluarsa',
    ];

    protected function casts(): array
    {
        return ['tanggal_survei' => 'date', 'token_kadaluarsa' => 'datetime'];
    }

    public function petugas()
    {
        return $this->belongsTo(Pengguna::class, 'id_petugas');
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah');
    }

    public function jawaban()
    {
        return $this->hasMany(Jawaban::class, 'id_sesi');
    }
}
