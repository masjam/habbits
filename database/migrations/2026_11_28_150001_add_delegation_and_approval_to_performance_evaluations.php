<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_assessor_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->string('period_type', 20);
            $table->string('period_label', 32);
            $table->foreignId('assessor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['academic_year', 'period_type', 'period_label', 'assessor_id', 'employee_id'],
                'perf_assign_unique'
            );
            $table->index(['assessor_id', 'status'], 'perf_assign_assessor_status_idx');
            $table->index('employee_id', 'perf_assign_employee_idx');
            $table->index(
                ['academic_year', 'period_type', 'period_label', 'employee_id', 'status'],
                'perf_assign_active_emp_idx'
            );
        });

        // Backfill periode sebelum mengubah academic_year & period_label menjadi NOT NULL
        $rows = DB::table('performance_evaluations')
            ->whereNull('academic_year')
            ->orWhereNull('period_label')
            ->get(['id', 'evaluation_month', 'evaluation_year', 'period_type', 'academic_year', 'period_label']);

        foreach ($rows as $row) {
            $m = (int) ($row->evaluation_month ?: 1);
            $y = (int) ($row->evaluation_year ?: date('Y'));
            $acadYear = $row->academic_year ?: ($m >= 7 ? "{$y}/" . ($y + 1) : ($y - 1) . "/{$y}");
            $periodLabel = $row->period_label ?: match ($row->period_type) {
                'semester_1' => 'semester_1',
                'semester_2' => 'semester_2',
                default => sprintf('%04d-%02d', $y, $m),
            };

            DB::table('performance_evaluations')
                ->where('id', $row->id)
                ->update([
                    'academic_year' => $acadYear,
                    'period_label'  => $periodLabel,
                ]);
        }

        // Drop unique index sementara sebelum mengubah nullability kolom di SQLite/MySQL
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropUnique('perf_eval_period_unique');
        });

        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->string('academic_year', 20)->nullable(false)->change();
            $table->string('period_label', 32)->nullable(false)->change();

            $table->foreignId('submitted_by')->nullable()->after('evaluator_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('revision_note')->nullable()->after('notes');
            $table->foreignId('assignment_id')->nullable()->after('revision_note')->constrained('performance_assessor_assignments')->nullOnDelete();
            $table->boolean('is_legacy')->default(false)->after('published_at');

            $table->unique(['user_id', 'academic_year', 'period_type', 'period_label'], 'perf_eval_period_unique');
        });

        // Backfill data published lama agar memiliki approved_by, approved_at, dan penanda legacy
        DB::table('performance_evaluations')
            ->where('status', 'published')
            ->whereNull('approved_at')
            ->update([
                'approved_by' => DB::raw('evaluator_id'),
                'approved_at' => DB::raw('COALESCE(published_at, updated_at, created_at)'),
                'is_legacy'   => true,
            ]);

        Schema::create('performance_evaluation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_evaluation_id')->constrained('performance_evaluations')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['performance_evaluation_id', 'created_at'], 'perf_eval_log_eval_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_evaluation_logs');

        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropUnique('perf_eval_period_unique');
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn([
                'submitted_at',
                'approved_at',
                'revision_note',
                'is_legacy',
            ]);

            $table->string('academic_year', 20)->nullable()->change();
            $table->string('period_label', 32)->nullable()->change();
            $table->unique(['user_id', 'academic_year', 'period_type', 'period_label'], 'perf_eval_period_unique');
        });

        Schema::dropIfExists('performance_assessor_assignments');
    }
};
