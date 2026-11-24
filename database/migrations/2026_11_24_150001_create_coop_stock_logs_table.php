<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coop_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coop_product_id')->constrained('coop_products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->default('opname'); // opname, restock, damage, expired, correction, sale
            $table->integer('stock_before')->default(0);
            $table->integer('stock_after')->default(0);
            $table->integer('difference')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_stock_logs');
    }
};
