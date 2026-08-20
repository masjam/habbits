<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menstruation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai')->nullable()
                  ->comment('NULL berarti user sedang dalam Mode Haid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menstruation_logs');
    }
};
