<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modul_survei', function (Blueprint $t) {
            $t->id();
            $t->string('kode');
            $t->string('nama');
            $t->string('versi')->default('v1');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('pertanyaan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_modul')->constrained('modul_survei');
            $t->string('kode_pertanyaan');
            $t->text('teks_pertanyaan');
            $t->enum('tipe_jawaban', ['angka', 'teks', 'pilihan', 'pilihan_ganda', 'json', 'validasi_jumlah']);
            $t->string('satuan', 20)->nullable();
            $t->boolean('wajib_diisi')->default(false);
            $t->json('aturan_validasi_json')->nullable();
            $t->unsignedSmallInteger('urutan')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // token_publik: URL yang dibagikan ke pengurus desa untuk pengisian via suara
        Schema::create('sesi_survei', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_petugas')->constrained('pengguna');
            $t->foreignId('id_wilayah')->constrained('wilayah');
            $t->year('tahun');
            $t->unsignedTinyInteger('bulan')->nullable();
            $t->date('tanggal_survei');
            $t->enum('status', ['draft', 'terkirim', 'disetujui', 'ditolak'])->default('draft');
            $t->text('catatan')->nullable();
            $t->string('id_perangkat')->nullable();
            $t->string('uuid_sesi_klien')->nullable();
            $t->string('token_publik', 64)->nullable()->unique();
            $t->dateTime('token_kadaluarsa')->nullable();
            $t->timestamps();
        });

        Schema::create('jawaban', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_sesi')->constrained('sesi_survei');
            $t->foreignId('id_modul')->constrained('modul_survei');
            $t->foreignId('id_pertanyaan')->constrained('pertanyaan');
            $t->decimal('nilai_angka', 18, 4)->nullable();
            $t->longText('nilai_teks')->nullable();
            $t->json('nilai_json')->nullable();
            $t->string('satuan', 20)->nullable();
            $t->enum('sumber', ['suara', 'manual'])->default('suara');
            $t->decimal('tingkat_keyakinan', 6, 4)->nullable();
            $t->timestamps();
            $t->unique(['id_sesi', 'id_pertanyaan']);
        });

        Schema::create('rekaman_suara', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_sesi')->constrained('sesi_survei');
            $t->foreignId('id_modul')->constrained('modul_survei');
            $t->string('path_audio')->nullable();
            $t->longText('teks_transkrip')->nullable();
            $t->string('penyedia_stt', 50)->nullable();
            $t->decimal('rata_keyakinan_stt', 6, 4)->nullable();
            $t->unsignedInteger('durasi_detik')->nullable();
            $t->timestamps();
        });

        // Populasi disimpan SATU KALI di sini, bukan diulang per komoditas.
        Schema::create('demografi_desa', function (Blueprint $t) {
            $t->id('id_demografi');
            $t->foreignId('id_wilayah')->constrained('wilayah');
            $t->foreignId('id_sesi_survei')->nullable()->constrained('sesi_survei');
            $t->year('tahun');
            $t->enum('kelompok_umur', ['balita', 'anak', 'remaja', 'dewasa', 'lansia']);
            $t->integer('jumlah_penduduk')->default(0);
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_wilayah', 'tahun', 'kelompok_umur']);
        });

        // Koefisien berversi: revisi tidak boleh mengubah hasil kalkulasi bulan lalu
        Schema::create('standar_kebutuhan_komoditas', function (Blueprint $t) {
            $t->id();
            $t->string('sektor', 50);
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->enum('kelompok_umur', ['balita', 'anak', 'remaja', 'dewasa', 'lansia']);
            $t->decimal('per_kapita_harian', 12, 6)->default(0);
            $t->string('satuan', 20);
            $t->string('sumber', 150)->nullable()->comment('AKG Kemenkes / survei lokal');
            $t->date('berlaku_mulai');
            $t->date('berlaku_sampai')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['id_komoditas', 'kelompok_umur', 'berlaku_mulai'], 'standar_kebutuhan_lookup');
        });

        Schema::create('kebutuhan_komoditas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_wilayah')->constrained('wilayah');
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->year('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->enum('kelompok_umur', ['balita', 'anak', 'remaja', 'dewasa', 'lansia']);
            $t->integer('jumlah_penduduk')->default(0);
            $t->decimal('per_kapita_harian', 12, 6)->default(0);
            $t->decimal('faktor_musiman', 6, 4)->default(1);
            $t->decimal('kebutuhan_bulanan', 18, 4)->default(0);
            $t->string('satuan', 20);
            $t->foreignId('id_standar')->nullable()->constrained('standar_kebutuhan_komoditas');
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_wilayah', 'id_komoditas', 'tahun', 'bulan', 'kelompok_umur'], 'kebutuhan_unik');
        });

        // Produksi per bulan panen. Padi tidak panen 12 kali setahun.
        Schema::create('ketersediaan_komoditas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_wilayah')->constrained('wilayah');
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->foreignId('id_sesi_survei')->nullable()->constrained('sesi_survei');
            $t->year('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->decimal('jumlah_produksi', 18, 4)->default(0);
            $t->string('satuan', 20);
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_wilayah', 'id_komoditas', 'tahun', 'bulan'], 'ketersediaan_unik');
        });

        Schema::create('hasil_kalkulasi', function (Blueprint $t) {
            $t->id('id_hasil');
            $t->foreignId('id_wilayah')->constrained('wilayah');
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->year('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->decimal('total_kebutuhan', 18, 4)->default(0);
            $t->decimal('total_ketersediaan', 18, 4)->default(0);
            $t->decimal('selisih', 18, 4)->default(0);
            $t->enum('status_surplus_defisit', ['surplus', 'defisit', 'seimbang']);
            $t->decimal('persentase_kecukupan', 8, 2)->default(0);
            $t->foreignId('id_unit_usaha_rekomendasi')->nullable()
              ->constrained('master_unit_usaha', 'id_unit_usaha');
            $t->text('alasan_rekomendasi')->nullable();
            $t->unsignedTinyInteger('prioritas')->nullable();
            $t->integer('versi')->default(1);
            $t->enum('status', ['draft', 'disetujui'])->default('draft');
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_wilayah', 'id_komoditas', 'tahun', 'bulan', 'versi'], 'hasil_unik');
        });

        Schema::create('perbandingan_harga', function (Blueprint $t) {
            $t->id('id_perbandingan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->foreignId('id_wilayah_sumber')->constrained('wilayah');
            $t->unsignedTinyInteger('bulan');
            $t->year('tahun');
            $t->decimal('harga_ditawarkan', 18, 2);
            $t->decimal('jumlah_tersedia', 18, 4)->default(0);
            $t->decimal('jarak_ke_gudang', 10, 2)->nullable();
            $t->decimal('estimasi_ongkir', 18, 2)->nullable();
            $t->decimal('harga_efektif', 18, 2)->comment('harga + ongkir per satuan');
            $t->unsignedTinyInteger('rank_harga')->nullable();
            $t->boolean('dipilih')->default(false);
            $t->timestamp('created_at')->nullable();
            $t->index(['id_koperasi', 'id_komoditas', 'tahun', 'bulan'], 'banding_lookup');
        });

        Schema::create('permintaan_pengadaan', function (Blueprint $t) {
            $t->id('id_permintaan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_permintaan', 30);
            $t->foreignId('id_pihak')->nullable()->constrained('master_pihak', 'id_pihak');
            $t->date('tgl_pengajuan');
            $t->decimal('total_nilai', 18, 2)->default(0);
            $t->enum('status', ['draft', 'diajukan', 'disetujui', 'ditolak', 'jadi_pembelian', 'dibatalkan'])
              ->default('draft');
            $t->text('catatan')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['id_koperasi', 'kode_permintaan']);
        });

        Schema::create('permintaan_pengadaan_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_permintaan')->constrained('permintaan_pengadaan', 'id_permintaan');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->foreignId('id_hasil')->nullable()->constrained('hasil_kalkulasi', 'id_hasil');
            $t->decimal('jumlah_diminta', 18, 4);
            $t->decimal('harga_perkiraan', 18, 2)->default(0);
            $t->decimal('subtotal', 18, 2)->default(0);
        });
    }

    public function down(): void
    {
        foreach ([
            'permintaan_pengadaan_detail', 'permintaan_pengadaan', 'perbandingan_harga',
            'hasil_kalkulasi', 'ketersediaan_komoditas', 'kebutuhan_komoditas',
            'standar_kebutuhan_komoditas', 'demografi_desa', 'rekaman_suara',
            'jawaban', 'sesi_survei', 'pertanyaan', 'modul_survei',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
