<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\HabitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerformanceBaselineLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Habit $habit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            HabitSeeder::class,
        ]);

        $badges = [
            [
                'name' => 'Pemula Istiqomah',
                'description' => 'Berhasil mencapai target skor 100 poin selama 3 hari berturut-turut.',
                'icon' => 'academic-cap', 
                'criteria_type' => 'streak_days',
                'criteria_value' => 3,
                'color_theme' => 'amber',
            ],
            [
                'name' => 'Pejuang 7 Hari',
                'description' => 'Mencapai target sempurna tanpa bolong selama 1 minggu berturut-turut.',
                'icon' => 'fire', 
                'criteria_type' => 'streak_days',
                'criteria_value' => 7,
                'color_theme' => 'orange',
            ],
        ];
        foreach ($badges as $b) {
            \App\Models\Badge::create($b);
        }

        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'monthly_target_score'], ['value' => '80']);
        Setting::updateOrCreate(['key' => 'feature_presensi'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'announcement_text'], ['value' => 'Pengumuman Penting']);
        Setting::updateOrCreate(['key' => 'push_notifications_active'], ['value' => '1']);

        // Create standard employee user
        $this->user = User::factory()->create([
            'name' => 'Ahmad Pegawai',
            'email' => 'ahmad@example.com',
            'gender' => 'L',
            'password' => Hash::make('password'),
        ]);
        $this->user->assignRole('user');

        // Create admin user
        $this->admin = User::factory()->create([
            'name' => 'Siti Admin',
            'email' => 'siti@example.com',
            'gender' => 'P',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole('admin');

        // Seed some habit logs
        $this->habit = Habit::first();
        HabitLog::create([
            'user_id' => $this->user->id,
            'habit_id' => $this->habit->id,
            'tanggal' => Carbon::today()->toDateString(),
            'nilai_input' => 3,
            'skor_diperoleh' => 2,
            'details' => ['subuh' => 'JM', 'dhuhur' => 'JM', 'asar' => 'JM'],
        ]);

        // Seed attendance
        Attendance::create([
            'user_id' => $this->user->id,
            'date' => Carbon::today()->toDateString(),
            'time_in' => '07:00:00',
            'time_out' => '15:00:00',
            'status' => 'hadir',
        ]);
    }

    public function test_shared_props_contract(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('auth', fn (Assert $auth) => $auth
                ->has('user')
                ->where('user.id', $this->user->id)
                ->where('user.email', $this->user->email)
                ->has('roles')
                ->has('is_actual_superadmin')
                ->has('simulated_role')
                ->has('multi_accounts')
                ->has('is_adding_account')
            )
            ->has('global_settings')
            ->where('global_settings.maintenance_mode', '0')
            ->where('global_settings.monthly_target_score', '80')
            ->has('webpush', fn (Assert $wp) => $wp
                ->has('vapid_public_key')
                ->where('is_active', true)
            )
            ->has('flash', fn (Assert $f) => $f
                ->has('success')
                ->has('error')
            )
            ->has('notifications', fn (Assert $n) => $n
                ->has('unread_count')
                ->has('latest')
            )
        );
    }

    public function test_user_dashboard_contract(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('skorHariIni')
            ->has('skorMaksimalHariIni')
            ->has('skorBulanIni')
            ->has('skorMaksimalBulanIni')
            ->has('skorBulanDipilih')
            ->has('skorMaksimalBulanDipilih')
            ->has('targetSkorMinimal')
            ->has('targetBulanan')
            ->has('adminTargetBulanan')
            ->has('adminTargetSkorMinimal')
            ->has('isPersonalTarget')
            ->has('isSedangHaid')
            ->has('dailyChartData')
            ->has('monthlyChartData')
            ->has('semester')
            ->has('tahun')
            ->has('leaderboard')
            ->has('isTrackingOther')
            ->has('targetUser')
            ->has('userBadges')
            ->has('insightMessage')
            ->has('pegawaiList')
            ->has('missedDates')
            ->has('announcement')
            ->has('habitAnalytics')
            ->has('radarChartDataBackend')
            ->has('hasClockedInToday')
            ->has('shouldShowPresensiNotif')
            ->has('featurePresensiActive')
            ->has('selectedMonth')
            ->has('selectedYear')
            ->has('presensiBulanIni')
            ->has('semesterPresensiSummary')
        );
    }

    public function test_admin_dashboard_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('skorHariIni')
            ->has('skorMaksimalHariIni')
            ->has('totalPegawai')
            ->has('dailyChartData')
            ->has('monthlyChartData')
            ->has('semester')
            ->has('tahun')
            ->has('targetRatio', fn (Assert $tr) => $tr
                ->has('hit')
                ->has('miss')
                ->has('target')
            )
        );
    }

    public function test_form_habit_contract(): void
    {
        $response = $this->actingAs($this->user)->get(route('habit.form'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FormHabit')
            ->has('habits')
            ->has('logsHariIni')
            ->has('isSedangHaid')
            ->has('waktuSelesaiHaid')
            ->has('waktuMulaiHaid')
            ->has('tanggal')
            ->has('today')
        );
    }

    public function test_notifications_contract(): void
    {
        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications')
        );
    }

    public function test_attendance_contract(): void
    {
        $response = $this->actingAs($this->user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->has('todayAttendance')
            ->has('monthlyAttendances')
            ->has('stats')
        );
    }

    public function test_admin_laporan_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.laporan'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Laporan/Index')
            ->has('leaderboard')
            ->has('targetBulanan')
            ->has('skorMaksimalSebulan')
            ->has('namaBulan')
            ->has('divisis')
            ->has('filters')
        );
    }
}
