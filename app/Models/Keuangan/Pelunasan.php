<?php

namespace App\Models\Keuangan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\KasBank;
use App\Models\Master\Pihak;
use Illuminate\Database\Eloquent\Model;

class Pelunasan extends Model
{
    use BelongsToKoperasi;

    protected $table = 'pelunasan';
    protected $primaryKey = 'id_pelunasan';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'kode_pelunasan', 'jenis', 'id_pihak', 'tanggal',
        'id_kas_bank', 'total_nilai', 'status_posting', 'id_jurnal',
        'catatan', 'created_by',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'total_nilai' => 'decimal:2'];
    }

    public function pihak()
    {
        return $this->belongsTo(Pihak::class, 'id_pihak', 'id_pihak');
    }

    public function kasBank()
    {
        return $this->belongsTo(KasBank::class, 'id_kas_bank', 'id_kas_bank');
    }

    public function detail()
    {
        return $this->hasMany(PelunasanDetail::class, 'id_pelunasan', 'id_pelunasan');
    }
}
