<?php

namespace App\Models\Keuangan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\KasBank;
use Illuminate\Database\Eloquent\Model;

class KasTransaksi extends Model
{
    use BelongsToKoperasi;

    protected $table = 'kas_transaksi';
    protected $primaryKey = 'id_kas_trx';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'kode_trx', 'tanggal', 'jenis', 'id_kas_bank',
        'id_kas_bank_tujuan', 'kode_akun_lawan', 'nilai', 'keterangan',
        'status_posting', 'id_jurnal', 'created_by',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'nilai' => 'decimal:2'];
    }

    public function kasBank()
    {
        return $this->belongsTo(KasBank::class, 'id_kas_bank', 'id_kas_bank');
    }
}
