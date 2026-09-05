<?php

namespace App\Models\Konsinyasi;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Tenant\KoperasiDesa;
use Illuminate\Database\Eloquent\Model;

/**
 * Stok titipan di gudang penerima — TERPISAH dari tabel stok karena BUKAN
 * aset desa penerima. Invariant: qty_titip = terjual + retur + susut + sisa.
 */
class StokKonsinyasi extends Model
{
    protected $table = 'stok_konsinyasi';
    protected $primaryKey = 'id_stok_konsinyasi';

    protected $fillable = [
        'id_kiriman', 'id_koperasi_pemilik', 'id_koperasi_penerima', 'id_gudang_penerima',
        'id_barang', 'qty_titip', 'qty_terjual', 'qty_retur', 'qty_susut', 'qty_sisa',
        'harga_titip_satuan', 'harga_jual_satuan', 'hpp_pemilik', 'status',
    ];

    protected function casts(): array
    {
        return [
            'qty_titip' => 'decimal:4',
            'qty_terjual' => 'decimal:4',
            'qty_retur' => 'decimal:4',
            'qty_susut' => 'decimal:4',
            'qty_sisa' => 'decimal:4',
            'harga_titip_satuan' => 'decimal:2',
            'harga_jual_satuan' => 'decimal:2',
            'hpp_pemilik' => 'decimal:4',
        ];
    }

    public function kiriman()
    {
        return $this->belongsTo(PengirimanKonsinyasi::class, 'id_kiriman', 'id_kiriman');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    // =========================================================================
    // LOCAL SCOPES
    // =========================================================================

    /**
     * Stok titipan yang DIMILIKI oleh koperasi aktif (sudah dikirim ke desa lain).
     * Contoh: halaman rekap aset titipan desa pemilik.
     */
    public function scopeMilikKoperasi($query): void
    {
        $query->where('id_koperasi_pemilik', app('koperasi_aktif'));
    }

    /**
     * Stok titipan yang BERADA di gudang koperasi aktif (diterima dari desa lain).
     * Contoh: halaman daftar stok titipan yang harus dijual.
     */
    public function scopeDiKoperasi($query): void
    {
        $query->where('id_koperasi_penerima', app('koperasi_aktif'));
    }

    /**
     * Semua stok titipan yang melibatkan koperasi aktif (pemilik ATAU penerima).
     */
    public function scopeTerlibat($query): void
    {
        $id = app('koperasi_aktif');
        $query->where(function ($q) use ($id) {
            $q->where('id_koperasi_pemilik', $id)
              ->orWhere('id_koperasi_penerima', $id);
        });
    }
}
