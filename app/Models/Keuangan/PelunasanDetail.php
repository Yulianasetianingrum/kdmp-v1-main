<?php

namespace App\Models\Keuangan;

use Illuminate\Database\Eloquent\Model;

class PelunasanDetail extends Model
{
    protected $table = 'pelunasan_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = ['id_pelunasan', 'id_piutang', 'id_hutang', 'nilai_bayar'];

    protected function casts(): array
    {
        return ['nilai_bayar' => 'decimal:2'];
    }

    public function pelunasan()
    {
        return $this->belongsTo(Pelunasan::class, 'id_pelunasan', 'id_pelunasan');
    }

    public function piutang()
    {
        return $this->belongsTo(Piutang::class, 'id_piutang', 'id_piutang');
    }

    public function hutang()
    {
        return $this->belongsTo(Hutang::class, 'id_hutang', 'id_hutang');
    }
}
