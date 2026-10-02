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
        Schema::create('teacher_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('class_name');
            $table->string('subject');
            $table->string('meeting_number');
            $table->string('period');
            $table->text('material');
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->integer('student_present')->default(0);
            $table->integer('student_sick')->default(0);
            $table->integer('student_leave')->default(0);
            $table->integer('student_absent')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_journals');
    }
};
