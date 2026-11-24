<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Produk Koperasi (Waroong POS Catalog)
        Schema::create('coop_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->string('barcode', 100)->nullable()->index();
            $table->string('category', 100)->default('Umum')->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->integer('stock')->default(100);
            $table->string('unit', 50)->default('Pcs');
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Transaksi Kasir POS Koperasi
        Schema::create('coop_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transaction_code', 60)->unique()->index();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Pegawai/Guru yang belanja
            $table->foreignId('voucher_id')->nullable()->constrained('habit_vouchers')->nullOnDelete(); // Voucher yang dipakai
            $table->string('payment_method', 50)->default('cash'); // 'cash', 'rfid_card', 'voucher', 'split', 'qris'
            $table->decimal('total_amount', 12, 2)->default(0); // Total belanja kotor
            $table->decimal('discount_amount', 12, 2)->default(0); // Potongan harga umum
            $table->decimal('voucher_amount', 12, 2)->default(0); // Potongan dari voucher habit
            $table->decimal('rfid_paid_amount', 12, 2)->default(0); // Nominal dibayar via saldo RFID
            $table->decimal('cash_paid_amount', 12, 2)->default(0); // Nominal tunai dibayar
            $table->decimal('change_amount', 12, 2)->default(0); // Kembalian tunai
            $table->json('items')->nullable(); // Rincian item barang belanjaan
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Tabel Mutasi Saldo Kartu RFID Koperasi Guru (Ledger)
        Schema::create('coop_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50); // 'voucher_conversion', 'pos_purchase', 'topup', 'adjustment', 'refund'
            $table->decimal('amount', 12, 2); // Nilai mutasi (+/-)
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('reference_id', 100)->nullable()->index(); // Kode voucher / Kode transaksi POS
            $table->string('description', 255)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_balance_logs');
        Schema::dropIfExists('coop_transactions');
        Schema::dropIfExists('coop_products');
    }
};
