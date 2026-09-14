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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            
            // Check-in (Masuk)
            $table->time('time_in')->nullable();
            $table->decimal('lat_in', 10, 7)->nullable();
            $table->decimal('lng_in', 10, 7)->nullable();
            $table->integer('distance_in')->nullable()->comment('Jarak dalam meter');
            $table->string('photo_in')->nullable()->comment('Path foto selfie jika dinas luar');
            
            // Check-out (Pulang)
            $table->time('time_out')->nullable();
            $table->decimal('lat_out', 10, 7)->nullable();
            $table->decimal('lng_out', 10, 7)->nullable();
            $table->integer('distance_out')->nullable()->comment('Jarak dalam meter');
            $table->string('photo_out')->nullable();
            
            // Status & Catatan
            $table->enum('status', ['hadir', 'terlambat', 'dinas_luar', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->text('notes')->nullable()->comment('Keterangan dinas luar / catatan');
            
            $table->timestamps();

            // 1 pegawai hanya 1 baris record per hari
            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
