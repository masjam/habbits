<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\TeacherAdministration;
use App\Models\TeacherJournal;
use App\Models\User;
use App\Services\PerformanceInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_attendance_habit_and_teacher_data_without_n_plus_one(): void
    {
        $teacher = User::factory()->create(['divisi' => 'Guru Kelas']);
        $habit = Habit::create([
            'nama_habit'    => 'Sholat Dhuha',
            'tipe_input'    => 'boolean',
            'skor_maksimal' => 10,
            'status_aktif'  => true,
        ]);

        Attendance::create([
            'user_id' => $teacher->id,
            'date'    => '2026-11-02',
            'status'  => 'hadir',
        ]);
        Attendance::create([
            'user_id' => $teacher->id,
            'date'    => '2026-11-03',
            'status'  => 'terlambat',
        ]);
        Attendance::create([
            'user_id' => $teacher->id,
            'date'    => '2026-11-04',
            'status'  => 'izin',
        ]);

        HabitLog::create([
            'user_id'        => $teacher->id,
            'habit_id'       => $habit->id,
            'tanggal'        => '2026-11-02',
            'nilai_input'    => 1,
            'skor_diperoleh' => 10,
        ]);

        TeacherAdministration::create([
            'user_id'        => $teacher->id,
            'academic_year'  => '2026/2027',
            'semester'       => 'ganjil',
            'category'       => 'modul_ajar',
            'title'          => 'Modul 1',
            'file_path'      => 'docs/m1.pdf',
            'file_name'      => 'm1.pdf',
            'file_extension' => 'pdf',
            'file_size'      => 1024,
            'status'         => 'disetujui',
        ]);

        TeacherJournal::create([
            'user_id'    => $teacher->id,
            'date'       => '2026-11-02',
            'class_name' => '1A',
            'subject'    => 'Matematika',
            'theme'      => 'Bilangan',
            'material'   => 'Penjumlahan',
        ]);

        $service = new PerformanceInsightService();

        DB::enableQueryLog();
        $insight = $service->forUser($teacher, '2026/2027', 'bulanan', '2026-11', 'guru');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($insight['attendance']['has_data']);
        $this->assertEquals(2, $insight['attendance']['hadir']);
        $this->assertEquals(1, $insight['attendance']['terlambat']);
        $this->assertEquals(1, $insight['attendance']['izin_sakit']);

        $this->assertTrue($insight['habit']['has_data']);
        $this->assertEquals(1, $insight['habit']['filled_days']);

        $this->assertTrue($insight['teacher_administration']['has_data']);
        $this->assertEquals(1, $insight['teacher_administration']['uploaded']);
        $this->assertEquals(1, $insight['teacher_administration']['approved']);

        $this->assertTrue($insight['teacher_journal']['has_data']);
        $this->assertEquals(1, $insight['teacher_journal']['total_entries']);

        // Pastikan hanya query agregasi konstan (<= 6 query)
        $this->assertLessThanOrEqual(6, count($queries));
    }

    public function test_returns_null_rates_when_no_data_exists(): void
    {
        $staff = User::factory()->create(['divisi' => 'Tata Usaha']);
        $service = new PerformanceInsightService();

        $insight = $service->forUser($staff, '2026/2027', 'bulanan', '2026-11', 'karyawan');

        $this->assertFalse($insight['attendance']['has_data']);
        $this->assertNull($insight['attendance']['attendance_rate']);
        $this->assertFalse($insight['habit']['has_data']);
        $this->assertNull($insight['habit']['consistency_rate']);
        $this->assertNull($insight['teacher_administration']);
        $this->assertNull($insight['teacher_journal']);
    }
}
