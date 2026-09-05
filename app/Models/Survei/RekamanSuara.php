<?php

namespace App\Models\Survei;

use Illuminate\Database\Eloquent\Model;

class RekamanSuara extends Model
{
    protected $table = 'rekaman_suara';

    protected $fillable = [
        'id_sesi', 'id_modul', 'path_audio', 'teks_transkrip', 'penyedia_stt',
        'rata_keyakinan_stt', 'durasi_detik',
    ];

    protected function casts(): array
    {
        return ['rata_keyakinan_stt' => 'decimal:4'];
    }

    public function sesi()
    {
        return $this->belongsTo(SesiSurvei::class, 'id_sesi');
    }
}
