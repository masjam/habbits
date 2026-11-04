<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('hadir', 'terlambat', 'dinas_luar', 'izin', 'sakit', 'alpa', 'cuti') NOT NULL DEFAULT 'hadir'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('hadir', 'terlambat', 'dinas_luar', 'izin', 'sakit', 'alpa') NOT NULL DEFAULT 'hadir'");
        }
    }
};

