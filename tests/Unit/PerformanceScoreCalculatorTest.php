<?php

namespace Tests\Unit;

use App\Services\PerformanceScoreCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class PerformanceScoreCalculatorTest extends TestCase
{
    private PerformanceScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PerformanceScoreCalculator();
    }

    public function test_calculates_guru_scores_and_sangat_baik_predicate(): void
    {
        $input = [];
        foreach (config('performance_indicators.categories.guru.aspects') as $aspect => $meta) {
            foreach ($meta['indicators'] as $ind) {
                $input[$aspect][$ind['code']] = 5;
            }
        }

        $result = $this->calculator->calculate('guru', $input, true);

        $this->assertEquals(100.0, $result['final_score']);
        $this->assertEquals('Sangat Baik', $result['predicate']);
        $this->assertEquals([
            'pedagogik'   => 100.0,
            'profesional' => 100.0,
            'kepribadian' => 100.0,
            'sosial'      => 100.0,
        ], $result['aspect_scores']);
    }

    public function test_calculates_mixed_likert_and_predicate_thresholds(): void
    {
        // Karyawan: aspek 1=4 (80), aspek 2=3 (60), aspek 3=5 (100), aspek 4=3 (60) => avg = 75.0 => Baik
        $aspects = config('performance_indicators.categories.karyawan.aspects');
        $values = [
            'kualitas_kuantitas_kerja' => 4,
            'kedisiplinan'             => 3,
            'inisiatif_tanggung_jawab' => 5,
            'pelayanan_kerja_sama'     => 3,
        ];

        $input = [];
        foreach ($aspects as $aspect => $meta) {
            foreach ($meta['indicators'] as $ind) {
                $input[$aspect][$ind['code']] = $values[$aspect];
            }
        }

        $result = $this->calculator->calculate('karyawan', $input, true);
        $this->assertEquals(75.0, $result['final_score']);
        $this->assertEquals('Baik', $result['predicate']);

        $this->assertEquals('Cukup', $this->calculator->resolvePredicate(60.0));
        $this->assertEquals('Kurang', $this->calculator->resolvePredicate(59.99));
    }

    public function test_rejects_invalid_indicator_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate('guru', [
            'pedagogik' => ['kode_ngasal' => 4],
        ], false);
    }

    public function test_requires_complete_indicators_when_published(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate('karyawan', [
            'kedisiplinan' => ['dis_kehadiran' => 4],
        ], true);
    }

    public function test_allows_partial_indicators_in_draft_mode(): void
    {
        $result = $this->calculator->calculate('karyawan', [
            'kedisiplinan' => ['dis_kehadiran' => 4],
        ], false);

        $this->assertEquals(80.0, $result['aspect_scores']['kedisiplinan']);
        $this->assertEquals(20.0, $result['final_score']);
    }
}
