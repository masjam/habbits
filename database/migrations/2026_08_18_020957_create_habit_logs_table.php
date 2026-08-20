<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->bigInteger('nilai_input')->default(0)
                  ->comment('Nilai aktual input user. BigInt untuk menampung nominal Rupiah.');
            $table->integer('skor_diperoleh')->default(0)
                  ->comment('Hasil kalkulasi otomatis: min(nilai_input/target_pencapaian, 1) * skor_maksimal');
            $table->timestamps();

            // Satu user hanya bisa mengisi satu habit per tanggal
            $table->unique(['user_id', 'habit_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};
