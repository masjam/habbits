<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel Surat Masuk
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat')->unique();
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima');
            $table->string('pengirim');
            $table->string('perihal');
            $table->string('file_path')->nullable();
            $table->enum('status', ['baru', 'didisposisikan', 'selesai'])->default('baru');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // siapa yang input
            $table->timestamps();
        });

        // Tabel Surat Keluar
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat')->unique();
            $table->date('tanggal_surat');
            $table->string('tujuan');
            $table->string('perihal');
            $table->string('file_path')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // siapa yang input
            $table->timestamps();
        });

        // Tabel Disposisi
        Schema::create('disposisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_masuk_id')->constrained('surat_masuk')->onDelete('cascade');
            $table->foreignId('pemberi_id')->constrained('users')->onDelete('cascade'); // Kepala Sekolah
            $table->foreignId('penerima_id')->constrained('users')->onDelete('cascade'); // Pegawai
            $table->text('instruksi');
            $table->date('batas_waktu')->nullable();
            $table->enum('status', ['menunggu', 'dibaca', 'dikerjakan', 'selesai'])->default('menunggu');
            $table->text('catatan_penyelesaian')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disposisi');
        Schema::dropIfExists('surat_keluar');
        Schema::dropIfExists('surat_masuk');
    }
};
