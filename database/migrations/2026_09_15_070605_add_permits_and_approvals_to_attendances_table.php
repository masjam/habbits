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
            $table->string('approval_status', 20)->default('approved')->after('status'); // approved, pending, rejected
            $table->string('approval_type', 30)->default('none')->after('approval_status'); // none, izin, sakit, pulang_mendahului
            $table->text('rejection_note')->nullable()->after('approval_type');
            $table->string('attachment')->nullable()->after('rejection_note');
            $table->boolean('is_early_departure')->default(false)->after('attachment');
            $table->text('early_departure_reason')->nullable()->after('is_early_departure');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('early_departure_reason');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'approval_type',
                'rejection_note',
                'attachment',
                'is_early_departure',
                'early_departure_reason',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
