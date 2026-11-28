<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CoopProduct;
use App\Models\CoopTransaction;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Setting;
use App\Models\TeacherAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'tata_usaha', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_each_role_receives_only_its_own_payload(): void
    {
        $cases = [
            ['user', 'Dashboard', 'user', ['tu', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'system']],
            ['tata_usaha', 'Dashboard', 'tata_usaha', ['koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'system']],
            ['koperasi', 'Dashboard', 'user', ['tu', 'koperasi', 'kurikulum', 'kepala_sekolah', 'admin', 'system']],
            ['kurikulum', 'Dashboard', 'kurikulum', ['tu', 'koperasi', 'kepala_sekolah', 'admin', 'system']],
            ['kepala_sekolah', 'Dashboard', 'kepala_sekolah', ['tu', 'koperasi', 'kurikulum', 'admin', 'system']],
            ['admin', 'Admin/Dashboard', 'admin', ['tu', 'koperasi', 'kurikulum', 'kepala_sekolah', 'system']],
            ['superadmin', 'Admin/Dashboard', 'superadmin', ['tu', 'koperasi', 'kurikulum', 'kepala_sekolah']],
        ];

        foreach ($cases as [$role, $component, $expectedKey, $forbiddenKeys]) {
            $u = User::factory()->create();
            $u->assignRole($role);

            $this->actingAs($u)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($component, $expectedKey, $forbiddenKeys) {
                    $page->component($component)
                        ->where('dashboard.roleKey', $expectedKey)
                        ->has('dashboard.personal');
                    foreach ($forbiddenKeys as $fk) {
                        $page->missing("dashboard.{$fk}");
                    }
                });
        }
    }

    public function test_koperasi_dashboard_aggregates_monthly_sales_low_stock_and_bon(): void
    {
        $koperasi = User::factory()->create();
        $koperasi->assignRole('koperasi');

        CoopProduct::create([
            'name' => 'Buku Tulis',
            'category' => 'ATK',
            'price' => 5000,
            'stock' => 2,
            'is_active' => true,
        ]);

        CoopTransaction::create([
            'transaction_code' => 'TRX-TEST-1',
            'cashier_id' => $koperasi->id,
            'payment_method' => 'bon',
            'total_amount' => 50000,
            'cash_paid_amount' => 0,
            'rfid_paid_amount' => 0,
            'voucher_amount' => 0,
            'bon_amount' => 50000,
            'bon_paid_amount' => 10000,
            'bon_status' => 'unpaid',
        ]);

        $this->actingAs($koperasi)
            ->get(route('koperasi.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Koperasi/Dashboard')
                ->where('dashboard.roleKey', 'koperasi')
                ->where('dashboard.koperasi.today_count', 1)
                ->where('dashboard.koperasi.today_sales', 50000)
                ->where('dashboard.koperasi.monthly_sales', 50000)
                ->where('dashboard.koperasi.low_stock_count', 1)
                ->where('dashboard.koperasi.outstanding_bon', 40000)
            );
    }

    public function test_multi_role_priority_and_simulated_role_are_respected(): void
    {
        $multi = User::factory()->create();
        $multi->assignRole(['user', 'kurikulum']);

        $this->actingAs($multi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.roleKey', 'user')
            );

        $this->actingAs($multi)
            ->get(route('kurikulum.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.roleKey', 'kurikulum')
            );

        $multiAdmin = User::factory()->create();
        $multiAdmin->assignRole(['user', 'admin']);

        $this->actingAs($multiAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('dashboard.roleKey', 'admin')
            );

        $this->actingAs($multiAdmin)
            ->get(route('pegawai.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'user')
            );

        $multiSuperadmin = User::factory()->create();
        $multiSuperadmin->assignRole(['user', 'superadmin']);

        $this->actingAs($multiSuperadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('dashboard.roleKey', 'superadmin')
            );

        $this->actingAs($multiSuperadmin)
            ->get(route('pegawai.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'user')
            );

        $sa = User::factory()->create();
        $sa->assignRole('superadmin');

        $this->actingAs($sa)
            ->withSession(['maintenance_simulated_role' => 'tata_usaha'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.roleKey', 'tata_usaha')
            );
    }

    public function test_disabled_feature_flags_skip_attendance_and_habit(): void
    {
        Setting::updateOrCreate(['key' => 'feature_presensi'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'feature_habit'], ['value' => '0']);
        Setting::clearCache();

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.personal.attendance_today', null)
                ->where('dashboard.personal.habit_today.max', 0)
            );
    }

    public function test_dashboard_query_count_is_bounded_without_n_plus_one(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employees = User::factory()->count(10)->create(['divisi' => 'Guru Kelas']);
        foreach ($employees as $emp) {
            $emp->assignRole('user');
            Attendance::create([
                'user_id' => $emp->id,
                'date' => now('Asia/Jakarta')->toDateString(),
                'time_in' => '07:00:00',
                'status' => 'hadir',
            ]);
        }

        $habit = Habit::create([
            'nama_habit' => 'Sholat Subuh',
            'skor_maksimal' => 10,
            'status_aktif' => true,
        ]);
        foreach ($employees as $emp) {
            HabitLog::create([
                'user_id' => $emp->id,
                'habit_id' => $habit->id,
                'tanggal' => now('Asia/Jakarta')->toDateString(),
                'nilai_input' => 1,
                'skor_diperoleh' => 10,
            ]);
        }

        TeacherAdministration::create([
            'user_id' => $employees[0]->id,
            'academic_year' => '2026/2027',
            'semester' => '1',
            'title' => 'RPP 1',
            'category' => 'RPP',
            'file_path' => 'docs/rpp.pdf',
            'file_name' => 'rpp.pdf',
            'status' => 'verified',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThan(25, count($queries), 'Dashboard admin melebihi batas query wajar.');
    }
}
