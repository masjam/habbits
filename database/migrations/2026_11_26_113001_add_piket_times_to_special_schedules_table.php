<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_schedules', function (Blueprint $table) {
            $table->string('piket_time_in', 10)->nullable()->after('time_out');
            $table->string('piket_time_out', 10)->nullable()->after('piket_time_in');
        });
    }

    public function down(): void
    {
        Schema::table('special_schedules', function (Blueprint $table) {
            $table->dropColumn(['piket_time_in', 'piket_time_out']);
        });
    }
};
