<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_vouchers', function (Blueprint $table) {
            $table->string('transaction_code', 50)->nullable()->unique()->after('redemption_notes');
            $table->decimal('transaction_amount', 12, 2)->default(0)->after('transaction_code');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('transaction_amount');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('discount_amount');
            $table->decimal('change_amount', 12, 2)->default(0)->after('paid_amount');
            $table->text('items_summary')->nullable()->after('change_amount');
        });
    }

    public function down(): void
    {
        Schema::table('habit_vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_code',
                'transaction_amount',
                'discount_amount',
                'paid_amount',
                'change_amount',
                'items_summary',
            ]);
        });
    }
};
