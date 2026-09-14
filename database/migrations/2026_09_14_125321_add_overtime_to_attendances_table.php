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
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_overtime')->default(false)->after('photo_out');
            $table->integer('overtime_minutes')->nullable()->after('is_overtime')->comment('Durasi lembur dalam menit jika pulang >60 menit');
            $table->text('overtime_activity')->nullable()->after('overtime_minutes')->comment('Deskripsi kegiatan lembur pegawai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['is_overtime', 'overtime_minutes', 'overtime_activity']);
        });
    }
};
