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
        Schema::table('employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_documents', 'status')) {
                $table->string('status', 20)->default('pending')->after('file_extension');
            }
        });

        // Sinkronisasi data lama
        DB::table('employee_documents')
            ->where('is_verified', true)
            ->update(['status' => 'verified']);

        DB::table('employee_documents')
            ->where('is_verified', false)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->update(['status' => 'rejected']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (Schema::hasColumn('employee_documents', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
