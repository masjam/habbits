<?php

namespace App\Services;

use InvalidArgumentException;

class PerformanceScoreCalculator
{
    /**
     * Hitung skor per aspek, nilai akhir, dan predikat dari input Likert 1–5.
     *
     * @param  array<string, array<string, mixed>>  $indicatorScores
     * @return array{aspect_scores: array<string, float>, final_score: float, predicate: string, rubric_version: string}
     */
    public function calculate(string $category, array $indicatorScores, bool $requireComplete = true): array
    {
        $rubric = app(PerformanceRubricService::class)->getRubric();
        $categories = $rubric['categories'] ?? [];
        if (!isset($categories[$category])) {
            throw new InvalidArgumentException("Kategori evaluasi '{$category}' tidak valid.");
        }

        $scaleMin = (int) ($rubric['scale']['min'] ?? 1);
        $scaleMax = (int) ($rubric['scale']['max'] ?? 5);
        $multiplier = (int) ($rubric['scale']['multiplier'] ?? 20);
        $aspectsConfig = $categories[$category]['aspects'] ?? [];

        $aspectScores = [];
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        // Tolak aspek liar yang tidak ada pada rubrik kategori
        foreach (array_keys($indicatorScores) as $submittedAspect) {
            if (!isset($aspectsConfig[$submittedAspect])) {
                throw new InvalidArgumentException("Aspek '{$submittedAspect}' tidak terdaftar pada rubrik kategori '{$category}'.");
            }
        }

        foreach ($aspectsConfig as $aspectKey => $aspectMeta) {
            $expectedCodes = collect($aspectMeta['indicators'] ?? [])->pluck('code')->all();
            $submitted = $indicatorScores[$aspectKey] ?? [];

            if (!is_array($submitted)) {
                throw new InvalidArgumentException("Data indikator untuk aspek '{$aspectKey}' harus berupa array.");
            }

            foreach ($submitted as $code => $value) {
                if (!in_array($code, $expectedCodes, true)) {
                    throw new InvalidArgumentException("Kode indikator '{$code}' tidak valid untuk aspek '{$aspectKey}'.");
                }
            }

            $scores = [];
            foreach ($expectedCodes as $code) {
                if (!array_key_exists($code, $submitted) || $submitted[$code] === null || $submitted[$code] === '') {
                    if ($requireComplete) {
                        throw new InvalidArgumentException("Indikator '{$code}' pada aspek '{$aspectKey}' wajib diisi sebelum publikasi.");
                    }
                    continue;
                }

                $val = $submitted[$code];
                if (filter_var($val, FILTER_VALIDATE_INT) === false) {
                    throw new InvalidArgumentException("Nilai indikator '{$code}' harus berupa bilangan bulat {$scaleMin}–{$scaleMax}.");
                }

                $intVal = (int) $val;
                if ($intVal < $scaleMin || $intVal > $scaleMax) {
                    throw new InvalidArgumentException("Nilai indikator '{$code}' harus di antara {$scaleMin} dan {$scaleMax}.");
                }

                $scores[] = $intVal * $multiplier;
            }

            $aspectAvg = count($scores) > 0
                ? round(array_sum($scores) / count($scores), 2)
                : 0.0;

            $aspectScores[$aspectKey] = $aspectAvg;

            $weight = (float) ($aspectMeta['weight'] ?? 0.25);
            $weightedSum += $aspectAvg * $weight;
            $totalWeight += $weight;
        }

        $finalScore = $totalWeight > 0
            ? round($weightedSum / $totalWeight, 2)
            : 0.0;

        return [
            'aspect_scores'  => $aspectScores,
            'final_score'    => $finalScore,
            'predicate'      => $this->resolvePredicate($finalScore),
            'rubric_version' => (string) ($rubric['version'] ?? '2026.1'),
        ];
    }

    public function resolvePredicate(float $finalScore): string
    {
        $predicates = config('performance_indicators.predicates', [
            ['min' => 90, 'label' => 'Sangat Baik'],
            ['min' => 75, 'label' => 'Baik'],
            ['min' => 60, 'label' => 'Cukup'],
            ['min' => 0,  'label' => 'Kurang'],
        ]);

        foreach ($predicates as $rule) {
            if ($finalScore >= (float) $rule['min']) {
                return (string) $rule['label'];
            }
        }

        return 'Kurang';
    }
}
