<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * List of tables to add UUID column.
     * 'notulens' is excluded because it already has a uuid column.
     * System/pivot tables (cache, jobs, sessions, model_has_*, role_has_*,
     * password_reset_tokens, push_subscriptions, notulen_user) are excluded.
     */
    protected array $tables = [
        'users',
        'habits',
        'habit_logs',
        'menstruation_logs',
        'settings',
        'badges',
        'user_badges',
        'login_logs',
        'attendances',
        'duty_schedules',
        'special_schedules',
        'notifications',
        'teacher_journals',
        'employee_documents',
        'performance_evaluations',
        'surat_masuk',
        'surat_keluar',
        'disposisi',
        'teacher_administrations',
        'divisions',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add nullable uuid column to each table (skip if already exists)
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'uuid')) {
                continue; // Already has uuid, skip
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->after('id')->nullable();
            });
        }

        // Step 2: Backfill existing records with unique UUIDs
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->whereNull('uuid')->get(['id']);
            foreach ($rows as $row) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        }

        // Step 3: Make uuid NOT NULL and UNIQUE
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Check if unique index already exists to avoid duplicate key error
            $indexName = $table . '_uuid_unique';
            $existingIndexes = collect(Schema::getIndexes($table))->pluck('name')->toArray();

            Schema::table($table, function (Blueprint $blueprint) use ($existingIndexes) {
                if (in_array('uuid', $existingIndexes) || in_array($blueprint->getTable() . '_uuid_unique', $existingIndexes)) {
                    // Only make not-null, index already exists
                    $blueprint->uuid('uuid')->nullable(false)->change();
                } else {
                    $blueprint->uuid('uuid')->nullable(false)->unique()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['uuid']);
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
