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
        Schema::table('notulens', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Pembuat notulen
            $table->boolean('is_approved')->default(false); // Status approval pimpinan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notulens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'is_approved']);
        });
    }
};
