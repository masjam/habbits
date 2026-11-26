<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Member Non-Pegawai Koperasi (Wali Murid, Siswa, Umum)
        Schema::create('coop_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('member_code', 40)->unique()->index();
            $table->string('name', 255);
            $table->string('type', 30)->default('umum')->index(); // wali_murid, siswa, umum
            $table->string('phone', 30)->nullable();
            $table->string('rfid_uid', 100)->nullable()->unique();
            $table->decimal('coop_balance', 12, 2)->default(0);
            $table->decimal('bon_limit', 12, 2)->default(0); // 0 = tanpa batas
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Dukungan Member Non-Pegawai & BON pada Transaksi POS
        Schema::table('coop_transactions', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('user_id')->constrained('coop_members')->nullOnDelete();
            $table->decimal('bon_amount', 12, 2)->default(0)->after('change_amount');
            $table->decimal('bon_paid_amount', 12, 2)->default(0)->after('bon_amount');
            $table->string('bon_status', 20)->nullable()->after('bon_paid_amount')->index(); // unpaid, partial, paid
        });

        // 3. Dukungan Mutasi Saldo untuk Member Non-Pegawai
        Schema::table('coop_balance_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('member_id')->nullable()->after('user_id')->constrained('coop_members')->nullOnDelete();
        });

        // 4. Tabel Pembayaran / Pelunasan BON (Piutang Belanja)
        Schema::create('coop_bon_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('coop_transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('coop_members')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30)->default('cash'); // cash, qris, rfid_card
            $table->string('notes', 255)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_bon_payments');

        Schema::table('coop_balance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });

        Schema::table('coop_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
            $table->dropColumn(['bon_amount', 'bon_paid_amount', 'bon_status']);
        });

        Schema::dropIfExists('coop_members');
    }
};
