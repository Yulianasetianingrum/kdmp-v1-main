<?php

namespace App\Models\Pembelian;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class ReturPembelian extends Model
{
    use BelongsToKoperasi;

    protected $table = 'retur_pembelian';
    protected $primaryKey = 'id_retur';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'kode_retur', 'id_pembelian', 'id_penerimaan', 'tgl_retur',
        'jenis_penyelesaian', 'total_nilai', 'alasan', 'foto_bukti', 'status',
        'status_posting', 'id_jurnal',
    ];

    protected function casts(): array
    {
        return ['tgl_retur' => 'date', 'total_nilai' => 'decimal:2'];
    }

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }

    public function detail()
    {
        return $this->hasMany(ReturPembelianDetail::class, 'id_retur', 'id_retur');
    }
}
