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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('document_type'); // KTP, KK, Ijazah SD, Ijazah SMP, Ijazah SMA, Ijazah S1, Ijazah S2, Sertifikat, dll
            $table->string('title');
            $table->string('document_number')->nullable();
            $table->string('document_year', 4)->nullable();
            $table->string('file_path');
            $table->string('file_extension')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
