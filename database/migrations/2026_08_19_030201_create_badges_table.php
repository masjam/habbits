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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Class icon (misal heroicons) atau URL path
            $table->string('criteria_type'); // misal: 'streak_days', 'total_points', 'total_days_perfect'
            $table->integer('criteria_value'); 
            $table->string('color_theme')->default('amber'); // Untuk styling (amber, emerald, blue, purple, dll)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
