<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('habit_voucher_logs')) {
            Schema::create('habit_voucher_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('habit_voucher_id')->constrained('habit_vouchers')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32); // generated | redeemed | unredeemed
                $table->string('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['habit_voucher_id', 'created_at']);
            });
        }

        try {
            Schema::table('habit_logs', function (Blueprint $table) {
                $table->index(['tanggal', 'user_id'], 'habit_logs_tanggal_user_idx');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('habit_vouchers', function (Blueprint $table) {
                $table->index(['period', 'status', 'user_id'], 'habit_vouchers_period_status_user_idx');
            });
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_voucher_logs');

        try {
            Schema::table('habit_logs', fn (Blueprint $table) => $table->dropIndex('habit_logs_tanggal_user_idx'));
        } catch (\Throwable) {}

        try {
            Schema::table('habit_vouchers', fn (Blueprint $table) => $table->dropIndex('habit_vouchers_period_status_user_idx'));
        } catch (\Throwable) {}
    }
};
