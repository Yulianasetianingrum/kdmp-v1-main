<?php

namespace App\Models\Keuangan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Pihak;
use Illuminate\Database\Eloquent\Model;

class Hutang extends Model
{
    use BelongsToKoperasi;

    protected $table = 'hutang';
    protected $primaryKey = 'id_hutang';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_pihak', 'sumber_tipe', 'sumber_id', 'kode_akun',
        'tanggal', 'tgl_jatuh_tempo', 'nilai_awal', 'nilai_terbayar', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tgl_jatuh_tempo' => 'date',
            'nilai_awal' => 'decimal:2',
            'nilai_terbayar' => 'decimal:2',
        ];
    }

    public function pihak()
    {
        return $this->belongsTo(Pihak::class, 'id_pihak', 'id_pihak');
    }

    public function sisa(): string
    {
        return bcsub((string) $this->nilai_awal, (string) $this->nilai_terbayar, 2);
    }
}
