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
            $table->mediumText('ttd_notulis')->nullable();
            $table->mediumText('ttd_pimpinan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notulens', function (Blueprint $table) {
            $table->dropColumn(['ttd_notulis', 'ttd_pimpinan']);
        });
    }
};
