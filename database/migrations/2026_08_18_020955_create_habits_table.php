<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->string('nama_habit');
            $table->string('deskripsi')->nullable()->comment('Petunjuk pengerjaan habit');
            $table->enum('tipe_input', ['boolean', 'integer'])->default('boolean')
                  ->comment('boolean: checklist ya/tidak, integer: isian jumlah');
            $table->string('satuan')->nullable()->comment('Waktu, Rakaat, Rupiah, Halaman, dsb');
            $table->integer('target_pencapaian')->default(1)
                  ->comment('Batas nilai input untuk mendapat skor maksimal');
            $table->integer('skor_maksimal')->default(0);
            $table->boolean('hide_saat_haid')->default(false)
                  ->comment('Jika true, habit disembunyikan saat Mode Haid');
            $table->boolean('is_pengganti_haid')->default(false)
                  ->comment('Jika true, habit hanya muncul saat Mode Haid');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
