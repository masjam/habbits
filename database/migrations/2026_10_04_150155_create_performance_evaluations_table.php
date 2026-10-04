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
        Schema::create('performance_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('evaluation_month');
            $table->integer('evaluation_year');
            $table->decimal('score_pedagogic', 5, 2)->default(0);
            $table->decimal('score_professional', 5, 2)->default(0);
            $table->decimal('score_personality', 5, 2)->default(0);
            $table->decimal('score_social', 5, 2)->default(0);
            $table->decimal('average_score', 5, 2)->default(0);
            $table->text('notes')->nullable();
            
            // Satu user hanya bisa dinilai 1 kali dalam bulan dan tahun yang sama
            $table->unique(['user_id', 'evaluation_month', 'evaluation_year'], 'perf_eval_unique');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_evaluations');
    }
};
