<?php

namespace App\Models\Akuntansi;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class ConfigShu extends Model
{
    use BelongsToKoperasi;

    protected $table = 'config_shu';
    protected $primaryKey = 'id_config';
    public $timestamps = false;

    protected $fillable = ['id_koperasi', 'tahun', 'pos', 'persentase', 'kode_akun'];

    protected function casts(): array
    {
        return ['persentase' => 'decimal:2'];
    }
}
