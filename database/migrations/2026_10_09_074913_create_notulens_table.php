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
        Schema::create('notulens', function (Blueprint $table) {
            $table->id();
            $table->string('judul_rapat');
            $table->string('jenis_rapat'); // Rutin Mingguan, Panitia Wisuda, dll
            $table->dateTime('tanggal_waktu');
            $table->string('pimpinan_rapat');
            $table->string('lokasi');
            $table->text('daftar_hadir')->nullable(); // Bisa dipisahkan koma atau JSON text
            $table->text('isi_pembahasan');
            $table->text('tindak_lanjut')->nullable();
            $table->json('dokumentasi')->nullable(); // Foto-foto rapat
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notulens');
    }
};
