<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved'])->default('draft')->after('file_path');
        });

        // Add kepala_sekolah role if not exists
        if (!Role::where('name', 'kepala_sekolah')->exists()) {
            Role::create(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        // We typically don't remove roles in down() as they might be assigned
    }
};
