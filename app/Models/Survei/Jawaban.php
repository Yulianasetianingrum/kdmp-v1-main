<?php

namespace App\Models\Survei;

use Illuminate\Database\Eloquent\Model;

class Jawaban extends Model
{
    protected $table = 'jawaban';

    protected $fillable = [
        'id_sesi', 'id_modul', 'id_pertanyaan', 'nilai_angka', 'nilai_teks',
        'nilai_json', 'satuan', 'sumber', 'tingkat_keyakinan',
    ];

    protected function casts(): array
    {
        return ['nilai_angka' => 'decimal:4', 'nilai_json' => 'array', 'tingkat_keyakinan' => 'decimal:4'];
    }

    public function sesi()
    {
        return $this->belongsTo(SesiSurvei::class, 'id_sesi');
    }

    public function pertanyaan()
    {
        return $this->belongsTo(Pertanyaan::class, 'id_pertanyaan');
    }
}
