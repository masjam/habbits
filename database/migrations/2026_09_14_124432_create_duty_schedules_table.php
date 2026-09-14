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
        Schema::create('duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->comment('1: Senin, 2: Selasa, 3: Rabu, 4: Kamis, 5: Jumat, 6: Sabtu, 7: Minggu');
            $table->string('name')->default('Piket Harian');
            $table->string('time_in', 10)->default('06:30');
            $table->string('time_out', 10)->default('14:30');
            $table->integer('late_tolerance')->nullable()->comment('Toleransi keterlambatan menit khusus piket');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('duty_schedule_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_schedule_id')->constrained('duty_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['duty_schedule_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duty_schedule_user');
        Schema::dropIfExists('duty_schedules');
    }
};
