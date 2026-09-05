<?php

namespace App\Models\Konsinyasi;

use App\Models\Tenant\KoperasiDesa;
use Illuminate\Database\Eloquent\Model;

class SetoranKonsinyasi extends Model
{
    protected $table = 'setoran_konsinyasi';
    protected $primaryKey = 'id_setoran';
    const UPDATED_AT = null;

    protected $fillable = [
        'kode_setoran', 'id_koperasi_penyetor', 'id_koperasi_penerima_dana', 'tanggal',
        'total_nilai', 'id_kas_bank_penyetor', 'id_kas_bank_penerima',
        'status_posting', 'id_jurnal_penyetor', 'id_jurnal_penerima', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'total_nilai' => 'decimal:2'];
    }

    public function koperasiPenyetor()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi_penyetor', 'id_koperasi');
    }

    public function koperasiPenerimaDana()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi_penerima_dana', 'id_koperasi');
    }
}
