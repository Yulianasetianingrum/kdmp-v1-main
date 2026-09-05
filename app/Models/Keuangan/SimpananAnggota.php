<?php

namespace App\Models\Keuangan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\KasBank;
use App\Models\Master\Pihak;
use Illuminate\Database\Eloquent\Model;

/**
 * Simpanan pokok & wajib -> MODAL (311/312).
 * Simpanan sukarela -> KEWAJIBAN (2115), karena bisa ditarik sewaktu-waktu.
 */
class SimpananAnggota extends Model
{
    use BelongsToKoperasi;

    protected $table = 'simpanan_anggota';
    protected $primaryKey = 'id_simpanan';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_pihak', 'jenis', 'tanggal', 'arah', 'nilai',
        'id_kas_bank', 'status_posting', 'id_jurnal',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'nilai' => 'decimal:2'];
    }

    public function pihak()
    {
        return $this->belongsTo(Pihak::class, 'id_pihak', 'id_pihak');
    }

    public function kasBank()
    {
        return $this->belongsTo(KasBank::class, 'id_kas_bank', 'id_kas_bank');
    }
}
