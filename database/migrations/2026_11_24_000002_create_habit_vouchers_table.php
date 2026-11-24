<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique()->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_code', 64)->unique();
            $table->string('period', 7); // YYYY-MM
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reward_title')->default('Voucher Apresiasi Target Habit');
            $table->string('reward_value')->default('Reward Apresiasi Sekolah');
            $table->decimal('target_percentage', 5, 2)->default(80.00);
            $table->decimal('achieved_percentage', 5, 2)->default(0.00);
            $table->integer('total_score')->default(0);
            $table->integer('max_possible_score')->default(0);
            $table->string('status', 20)->default('active'); // 'active', 'claimed', 'expired'
            $table->timestamp('claimed_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('qr_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index(['status', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_vouchers');
    }
};
