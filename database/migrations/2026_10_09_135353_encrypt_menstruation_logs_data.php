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
        // Change column type to TEXT to accommodate encrypted strings
        Schema::table('menstruation_logs', function (Blueprint $table) {
            $table->text('waktu_mulai')->change();
            $table->text('waktu_selesai')->nullable()->change();
        });

        // Encrypt existing data
        $logs = \Illuminate\Support\Facades\DB::table('menstruation_logs')->get();
        foreach ($logs as $log) {
            $updateData = [];
            
            if ($log->waktu_mulai) {
                $updateData['waktu_mulai'] = encrypt($log->waktu_mulai);
            }
            if ($log->waktu_selesai) {
                $updateData['waktu_selesai'] = encrypt($log->waktu_selesai);
            }

            if (!empty($updateData)) {
                \Illuminate\Support\Facades\DB::table('menstruation_logs')
                    ->where('id', $log->id)
                    ->update($updateData);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Decrypt existing data
        $logs = \Illuminate\Support\Facades\DB::table('menstruation_logs')->get();
        foreach ($logs as $log) {
            $updateData = [];
            
            if ($log->waktu_mulai) {
                try {
                    $updateData['waktu_mulai'] = decrypt($log->waktu_mulai);
                } catch (\Exception $e) {}
            }
            if ($log->waktu_selesai) {
                try {
                    $updateData['waktu_selesai'] = decrypt($log->waktu_selesai);
                } catch (\Exception $e) {}
            }

            if (!empty($updateData)) {
                \Illuminate\Support\Facades\DB::table('menstruation_logs')
                    ->where('id', $log->id)
                    ->update($updateData);
            }
        }

        Schema::table('menstruation_logs', function (Blueprint $table) {
            $table->dateTime('waktu_mulai')->change();
            $table->dateTime('waktu_selesai')->nullable()->change();
        });
    }
};
