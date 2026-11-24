<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_vouchers', function (Blueprint $table) {
            $table->foreignId('generated_by')->nullable()->after('qr_payload')->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable()->after('generated_by');
            $table->foreignId('redeemed_by')->nullable()->after('generated_at')->constrained('users')->nullOnDelete();
            $table->text('redemption_notes')->nullable()->after('redeemed_by');
        });
    }

    public function down(): void
    {
        Schema::table('habit_vouchers', function (Blueprint $table) {
            $table->dropForeign(['generated_by']);
            $table->dropForeign(['redeemed_by']);
            $table->dropColumn(['generated_by', 'generated_at', 'redeemed_by', 'redemption_notes']);
        });
    }
};
