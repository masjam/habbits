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
        Schema::table('habits', function (Blueprint $table) {
            $table->string('template')->default('default')->after('nama_habit')
                  ->comment('Tipe UI: sholat_wajib, sholat_rawatib, quran, default');
            $table->json('json_schema')->nullable()->after('template')
                  ->comment('Opsi spesifik untuk frontend jika perlu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->dropColumn(['template', 'json_schema']);
        });
    }
};
