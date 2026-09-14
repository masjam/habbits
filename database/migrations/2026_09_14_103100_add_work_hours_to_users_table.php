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
        Schema::table('users', function (Blueprint $table) {
            $table->time('work_start')->nullable()->after('target_tidak_aktif')->comment('Jam mulai kerja perseorangan (null = ikuti global)');
            $table->time('work_end')->nullable()->after('work_start')->comment('Jam selesai kerja perseorangan (null = ikuti global)');
            $table->integer('late_tolerance')->nullable()->after('work_end')->comment('Toleransi telat menit perseorangan (null = ikuti global)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['work_start', 'work_end', 'late_tolerance']);
        });
    }
};
