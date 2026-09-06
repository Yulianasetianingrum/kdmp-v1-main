<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SurveiSeeder extends Seeder
{
    public function run(): void
    {
        $modulId = DB::table('modul_survei')->insertGetId([
            'kode' => 'M1',
            'nama' => 'Survei Bulanan KDMP',
            'versi' => 'v1',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('pertanyaan')->insert([
            [
                'id_modul' => $modulId,
                'kode_pertanyaan' => 'DEMO-01',
                'teks_pertanyaan' => 'Berapa total penduduk balita di dusun ini?',
                'tipe_jawaban' => 'angka',
                'satuan' => 'Jiwa',
                'wajib_diisi' => true,
                'urutan' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_modul' => $modulId,
                'kode_pertanyaan' => 'PROD-01',
                'teks_pertanyaan' => 'Berapa perkiraan total panen beras bulan ini?',
                'tipe_jawaban' => 'angka',
                'satuan' => 'Ton',
                'wajib_diisi' => true,
                'urutan' => 2,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_modul' => $modulId,
                'kode_pertanyaan' => 'HARGA-01',
                'teks_pertanyaan' => 'Berapa harga jual telur ayam eceran di pasar saat ini?',
                'tipe_jawaban' => 'angka',
                'satuan' => 'Rupiah',
                'wajib_diisi' => true,
                'urutan' => 3,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_modul' => $modulId,
                'kode_pertanyaan' => 'UMUM-01',
                'teks_pertanyaan' => 'Adakah catatan tambahan terkait kondisi cuaca atau kejadian luar biasa yang mempengaruhi panen?',
                'tipe_jawaban' => 'teks',
                'satuan' => null,
                'wajib_diisi' => false,
                'urutan' => 4,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
