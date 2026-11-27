<?php

use App\Models\User;
use App\Services\PerformanceScoreCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_evaluations', 'uuid')) {
                $table->string('uuid')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('performance_evaluations', 'academic_year')) {
                $table->string('academic_year', 20)->nullable()->after('evaluator_id');
                $table->string('period_type', 20)->default('bulanan')->after('academic_year');
                $table->string('period_label', 32)->nullable()->after('period_type');
                $table->string('category', 20)->default('guru')->after('period_label');
                $table->string('rubric_version', 20)->default('2026.1')->after('category');
                $table->json('indicator_scores')->nullable()->after('rubric_version');
                $table->json('aspect_scores')->nullable()->after('indicator_scores');
                $table->decimal('final_score', 5, 2)->default(0)->after('aspect_scores');
                $table->string('predicate', 30)->nullable()->after('final_score');
                $table->string('status', 20)->default('draft')->after('notes');
                $table->timestamp('published_at')->nullable()->after('status');
                $table->softDeletes();

                $table->integer('evaluation_month')->nullable()->change();
                $table->integer('evaluation_year')->nullable()->change();
            }
        });

        // Backfill data evaluasi lama agar tetap valid dan terlihat oleh pegawai
        $calculator = new PerformanceScoreCalculator();
        $rows = DB::table('performance_evaluations')->get();
        foreach ($rows as $row) {
            $m = (int) ($row->evaluation_month ?: 1);
            $y = (int) ($row->evaluation_year ?: date('Y'));
            $acadYear = $m >= 7 ? "{$y}/" . ($y + 1) : ($y - 1) . "/{$y}";
            $periodLabel = sprintf('%04d-%02d', $y, $m);

            $user = User::find($row->user_id);
            $category = $user ? $user->evaluationCategory() : 'guru';

            $aspectScores = $category === 'guru'
                ? [
                    'pedagogik'   => (float) $row->score_pedagogic,
                    'profesional' => (float) $row->score_professional,
                    'kepribadian' => (float) $row->score_personality,
                    'sosial'      => (float) $row->score_social,
                ]
                : [
                    'kualitas_kuantitas_kerja' => (float) $row->score_pedagogic,
                    'kedisiplinan'             => (float) $row->score_professional,
                    'inisiatif_tanggung_jawab' => (float) $row->score_personality,
                    'pelayanan_kerja_sama'     => (float) $row->score_social,
                ];

            $finalScore = (float) $row->average_score;

            DB::table('performance_evaluations')
                ->where('id', $row->id)
                ->update([
                    'uuid'           => $row->uuid ?: (string) Str::uuid(),
                    'academic_year'  => $acadYear,
                    'period_type'    => 'bulanan',
                    'period_label'   => $periodLabel,
                    'category'       => $category,
                    'rubric_version' => (string) config('performance_indicators.version', '2026.1'),
                    'aspect_scores'  => json_encode($aspectScores),
                    'final_score'    => $finalScore,
                    'predicate'      => $calculator->resolvePredicate($finalScore),
                    'status'         => 'published',
                    'published_at'   => $row->created_at ?? now(),
                ]);
        }

        // Tambah indeks baru dulu agar MySQL tetap punya backing index untuk FK user_id sebelum drop perf_eval_unique
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->index('user_id', 'perf_eval_user_idx');
            $table->unique(['user_id', 'academic_year', 'period_type', 'period_label'], 'perf_eval_period_unique');
            $table->index(['academic_year', 'category', 'status'], 'perf_eval_filter_idx');
        });

        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropUnique('perf_eval_unique');
        });
    }

    public function down(): void
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->unique(['user_id', 'evaluation_month', 'evaluation_year'], 'perf_eval_unique');
        });

        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropUnique('perf_eval_period_unique');
            $table->dropIndex('perf_eval_filter_idx');
            $table->dropIndex('perf_eval_user_idx');

            $table->dropSoftDeletes();
            $table->dropColumn([
                'academic_year',
                'period_type',
                'period_label',
                'category',
                'rubric_version',
                'indicator_scores',
                'aspect_scores',
                'final_score',
                'predicate',
                'status',
                'published_at',
            ]);
        });
    }
};
