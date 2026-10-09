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
            $table->uuid('uuid')->after('id')->nullable();
        });

        // Populate existing records with UUIDs
        $notulens = \Illuminate\Support\Facades\DB::table('notulens')->get();
        foreach ($notulens as $notulen) {
            \Illuminate\Support\Facades\DB::table('notulens')
                ->where('id', $notulen->id)
                ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }

        Schema::table('notulens', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notulens', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
