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
            $table->string('nip')->nullable()->after('email');
            $table->string('divisi')->nullable()->after('nip');
            $table->string('status_kehadiran')->default('Aktif')->after('divisi');
            $table->text('catatan_pimpinan')->nullable()->after('personal_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'divisi', 'status_kehadiran', 'catatan_pimpinan']);
        });
    }
};
