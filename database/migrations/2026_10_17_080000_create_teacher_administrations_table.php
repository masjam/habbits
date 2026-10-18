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
        Schema::create('teacher_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('academic_year', 20); // Contoh: "2024/2025", "2025/2026"
            $table->string('semester', 10); // "ganjil" atau "genap"
            $table->string('grade_level', 50)->nullable(); // "Kelas 1", "Kelas 2", "Umum / Semua Kelas"
            $table->string('subject', 100)->nullable(); // Mata Pelajaran
            $table->string('category', 50); // modul_ajar, rpp, prota, promes, atp_cp, kktp, kisi_soal, buku_nilai, lainnya
            $table->string('title');
            $table->text('description')->nullable();
            
            // File Metadata
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // Dalam bytes

            // Verifikasi / Telaah oleh KS / Kurikulum / Admin
            $table->string('status', 30)->default('menunggu_verifikasi'); // menunggu_verifikasi, disetujui, perlu_revisi
            $table->text('feedback')->nullable(); // Catatan telaah jika perlu revisi atau apresiasi
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Indexes for fast searching and filtering
            $table->index(['user_id', 'academic_year', 'semester']);
            $table->index(['academic_year', 'semester', 'category']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_administrations');
    }
};
