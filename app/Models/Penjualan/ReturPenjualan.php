<?php

namespace App\Models\Penjualan;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class ReturPenjualan extends Model
{
    use BelongsToKoperasi;

    protected $table = 'retur_penjualan';
    protected $primaryKey = 'id_retur';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'kode_retur', 'id_penjualan', 'tgl_retur', 'jenis_penyelesaian',
        'total_nilai', 'total_hpp', 'alasan', 'status', 'status_posting', 'id_jurnal',
    ];

    protected function casts(): array
    {
        return ['tgl_retur' => 'date', 'total_nilai' => 'decimal:2', 'total_hpp' => 'decimal:2'];
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function detail()
    {
        return $this->hasMany(ReturPenjualanDetail::class, 'id_retur', 'id_retur');
    }
}
