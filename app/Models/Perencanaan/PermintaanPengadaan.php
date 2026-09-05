<?php

namespace App\Models\Perencanaan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Pihak;
use Illuminate\Database\Eloquent\Model;

class PermintaanPengadaan extends Model
{
    use BelongsToKoperasi;

    protected $table = 'permintaan_pengadaan';
    protected $primaryKey = 'id_permintaan';

    protected $fillable = [
        'id_koperasi', 'kode_permintaan', 'id_pihak', 'tgl_pengajuan', 'total_nilai',
        'status', 'catatan', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'tgl_pengajuan' => 'date',
            'total_nilai' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function pihak()
    {
        return $this->belongsTo(Pihak::class, 'id_pihak', 'id_pihak');
    }

    public function detail()
    {
        return $this->hasMany(PermintaanPengadaanDetail::class, 'id_permintaan', 'id_permintaan');
    }
}
