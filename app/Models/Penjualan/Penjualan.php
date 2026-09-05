<?php

namespace App\Models\Penjualan;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Gudang;
use App\Models\Master\KasBank;
use App\Models\Master\Pihak;
use App\Models\Master\UnitUsaha;
use Illuminate\Database\Eloquent\Model;

/**
 * Penjualan ke warga SELALU tunai/transfer (tidak ada JKW).
 * is_pembeli_anggota DISALIN saat transaksi — dasar perhitungan jasa anggota
 * untuk pembagian SHU, tidak boleh di-join dari master_pihak saat pelaporan.
 */
class Penjualan extends Model
{
    use BelongsToKoperasi;

    protected $table = 'penjualan';
    protected $primaryKey = 'id_penjualan';

    protected $fillable = [
        'id_koperasi', 'kode_penjualan', 'id_pihak', 'id_unit_usaha', 'id_gudang',
        'tanggal_transaksi', 'is_pembeli_anggota', 'ada_baris_konsinyasi',
        'id_kas_bank', 'metode_bayar', 'total_bruto', 'diskon', 'total_bayar',
        'total_hpp', 'status', 'status_posting', 'id_jurnal', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transaksi' => 'date',
            'is_pembeli_anggota' => 'boolean',
            'ada_baris_konsinyasi' => 'boolean',
            'total_bruto' => 'decimal:2',
            'diskon' => 'decimal:2',
            'total_bayar' => 'decimal:2',
            'total_hpp' => 'decimal:2',
        ];
    }

    public function pihak()
    {
        return $this->belongsTo(Pihak::class, 'id_pihak', 'id_pihak');
    }

    public function unitUsaha()
    {
        return $this->belongsTo(UnitUsaha::class, 'id_unit_usaha', 'id_unit_usaha');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'id_gudang', 'id_gudang');
    }

    public function kasBank()
    {
        return $this->belongsTo(KasBank::class, 'id_kas_bank', 'id_kas_bank');
    }

    public function detail()
    {
        return $this->hasMany(DetailPenjualan::class, 'id_penjualan', 'id_penjualan');
    }
}
