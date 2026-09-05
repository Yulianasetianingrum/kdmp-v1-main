<?php

namespace App\Models\Survei;

use Illuminate\Database\Eloquent\Model;

class Pertanyaan extends Model
{
    protected $table = 'pertanyaan';

    protected $fillable = [
        'id_modul', 'kode_pertanyaan', 'teks_pertanyaan', 'tipe_jawaban', 'satuan',
        'wajib_diisi', 'aturan_validasi_json', 'urutan', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'wajib_diisi' => 'boolean',
            'aturan_validasi_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function modul()
    {
        return $this->belongsTo(ModulSurvei::class, 'id_modul');
    }
}
