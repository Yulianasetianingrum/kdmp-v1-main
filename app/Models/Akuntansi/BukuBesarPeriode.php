<?php

namespace App\Models\Akuntansi;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

/**
 * Ringkasan saldo per akun per bulan. Selalu bisa dibangun ULANG dari
 * jurnal_header/jurnal_detail lewat TutupBukuService::bangunBukuBesar()
 * (idempoten, lihat catatan di dokumen pembelajaran modul Finance).
 */
class BukuBesarPeriode extends Model
{
    use BelongsToKoperasi;
    protected $table = 'buku_besar_periode';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id_koperasi', 'periode_tahun', 'periode_bulan', 'kode_anak',
        'saldo_awal_debet', 'saldo_awal_kredit', 'mutasi_debet', 'mutasi_kredit',
        'saldo_akhir_debet', 'saldo_akhir_kredit', 'dihitung_pada',
    ];

    protected function casts(): array
    {
        return ['dihitung_pada' => 'datetime'];
    }
}
