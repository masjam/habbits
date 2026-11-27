<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coop_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('discount_type')->default('percentage'); // percentage | fixed
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->decimal('min_purchase', 12, 2)->default(0);
            $table->string('member_type')->nullable(); // null = semua, pegawai, wali_murid, siswa, umum, registered
            $table->string('product_category')->nullable(); // null = semua kategori
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('coop_transactions', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->after('voucher_id')->constrained('coop_discounts')->nullOnDelete();
            $table->string('discount_name')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('coop_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn('discount_name');
        });

        Schema::dropIfExists('coop_discounts');
    }
};
