<?php

namespace App\Models\Pembelian;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Gudang;
use App\Models\Master\KasBank;
use App\Models\Master\Pihak;
use App\Models\Master\UnitUsaha;
use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    use BelongsToKoperasi;

    protected $table = 'pembelian';
    protected $primaryKey = 'id_pembelian';

    protected $fillable = [
        'id_koperasi', 'kode_pembelian', 'id_permintaan', 'id_pihak', 'id_unit_usaha',
        'id_gudang', 'tanggal_transaksi', 'jenis_pembayaran', 'id_kas_bank',
        'tgl_jatuh_tempo', 'total_pembelian', 'status', 'status_posting',
        'id_jurnal', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transaksi' => 'date',
            'tgl_jatuh_tempo' => 'date',
            'total_pembelian' => 'decimal:2',
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
        return $this->hasMany(DetailPembelian::class, 'id_pembelian', 'id_pembelian');
    }
}
