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
        Schema::table('habit_logs', function (Blueprint $table) {
            $table->index(['tanggal', 'user_id'], 'habit_logs_tanggal_user_id_index');
            $table->index(['user_id', 'tanggal'], 'habit_logs_user_id_tanggal_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habit_logs', function (Blueprint $table) {
            $table->dropIndex('habit_logs_tanggal_user_id_index');
            $table->dropIndex('habit_logs_user_id_tanggal_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_at_index');
        });
    }
};
