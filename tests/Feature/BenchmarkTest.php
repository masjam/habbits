<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\HabitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_measure_baseline_metrics()
    {
        $this->seed([
            RoleSeeder::class,
            HabitSeeder::class,
        ]);

        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'monthly_target_score'], ['value' => '80']);
        Setting::updateOrCreate(['key' => 'feature_presensi'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'announcement_text'], ['value' => 'Pengumuman Penting']);
        Setting::updateOrCreate(['key' => 'push_notifications_active'], ['value' => '1']);

        // Seed 100 users with realistic data
        $users = [];
        $now = now();
        $password = Hash::make('password');
        
        $userData = [];
        for ($i = 1; $i <= 50; $i++) {
            $userData[] = [
                'uuid' => (string) Str::uuid(),
                'name' => "Pegawai $i",
                'email' => "pegawai{$i}@example.com",
                'password' => $password,
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        User::insert($userData);

        $roleId = DB::table('roles')->where('name', 'user')->value('id');
        $allUsers = User::all();
        $userRoleData = [];
        foreach ($allUsers as $u) {
            $userRoleData[] = [
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $u->id,
            ];
        }
        DB::table('model_has_roles')->insert($userRoleData);

        $admin = User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin_test@example.com',
            'gender' => 'L',
        ]);
        $admin->assignRole('admin');

        $testUser = $allUsers->first();
        $habits = Habit::all();

        // Seed habit logs for past 30 days
        $logData = [];
        $today = Carbon::today();
        for ($day = 0; $day < 30; $day++) {
            $date = $today->copy()->subDays($day)->toDateString();
            foreach ($habits->take(5) as $h) {
                $logData[] = [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $testUser->id,
                    'habit_id' => $h->id,
                    'tanggal' => $date,
                    'nilai_input' => 3,
                    'skor_diperoleh' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        // Seed some for other users
        foreach ($allUsers->skip(1)->take(20) as $otherUser) {
            for ($day = 0; $day < 5; $day++) {
                $date = $today->copy()->subDays($day)->toDateString();
                $logData[] = [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $otherUser->id,
                    'habit_id' => $habits->first()->id,
                    'tanggal' => $date,
                    'nilai_input' => 2,
                    'skor_diperoleh' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        HabitLog::insert($logData);

        // Seed attendances
        $attData = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $today->copy()->subDays($day)->toDateString();
            $attData[] = [
                'uuid' => (string) Str::uuid(),
                'user_id' => $testUser->id,
                'date' => $date,
                'time_in' => '07:00:00',
                'time_out' => '15:00:00',
                'status' => 'hadir',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Attendance::insert($attData);

        // Helper to benchmark a route
        $benchmark = function ($userToActAs, $routeName, $params = []) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $queries = [];
            DB::listen(function ($query) use (&$queries) {
                $queries[] = $query->sql;
            });

            $start = microtime(true);
            $response = $this->actingAs($userToActAs)->get(route($routeName, $params));
            $duration = (microtime(true) - $start) * 1000;

            DB::disableQueryLog();

            $response->assertStatus(200);

            return [
                'route' => $routeName,
                'query_count' => count($queries),
                'duration_ms' => round($duration, 2),
                'queries' => $queries,
            ];
        };

        $routesToMeasure = [
            ['user' => $testUser, 'name' => 'dashboard', 'params' => [], 'label' => 'Dashboard Pegawai'],
            ['user' => $admin, 'name' => 'dashboard', 'params' => [], 'label' => 'Dashboard Admin'],
            ['user' => $testUser, 'name' => 'habit.form', 'params' => [], 'label' => 'Form Habit'],
            ['user' => $testUser, 'name' => 'attendance.index', 'params' => [], 'label' => 'Presensi Pegawai'],
            ['user' => $admin, 'name' => 'admin.attendance.index', 'params' => [], 'label' => 'Presensi Admin (Report)'],
            ['user' => $admin, 'name' => 'admin.laporan', 'params' => [], 'label' => 'Laporan Admin (Leaderboard)'],
            ['user' => $testUser, 'name' => 'notifications.index', 'params' => [], 'label' => 'Notifikasi / Pesan'],
        ];

        $results = [];
        foreach ($routesToMeasure as $item) {
            $res = $benchmark($item['user'], $item['name'], $item['params']);
            $results[] = [
                'label' => $item['label'],
                'query_count' => $res['query_count'],
                'duration_ms' => $res['duration_ms'],
            ];
        }

        $markdown = "# Performance Baseline Report\n\n";
        $markdown .= "Generated at: " . now()->toIso8601String() . "\n";
        $markdown .= "Environment: Laravel 13, PHP 8.3, SQLite In-Memory Test Suite (50 realistic users, 30 days logs & attendances)\n\n";
        $markdown .= "## 1. Database Queries & Response Times (Baseline)\n\n";
        $markdown .= "| Halaman / Route | User Context | Jumlah Query | Waktu Respons (ms) |\n";
        $markdown .= "| :--- | :--- | :---: | :---: |\n";
        foreach ($results as $r) {
            $markdown .= sprintf("| %s | %s | %d | %.2f ms |\n", $r['label'], str_contains($r['label'], 'Admin') ? 'Admin' : 'Pegawai', $r['query_count'], $r['duration_ms']);
        }
        $markdown .= "\n### Detail Baseline Query Findings\n";
        $markdown .= "- **Shared Props & Global Middleware**: Setiap request menjalankan query `Setting::where('key', 'maintenance_mode')`, `Setting::pluck('value', 'key')`, roles via spatie, dan unread notifications count.\n";
        $markdown .= "- **Dashboard Pegawai**: Mengalami pembacaan `Setting` berulang (`feature_presensi` x2, `announcement_text`, `monthly_target_score`), `User::getEffectiveWorkSchedule` (menjalankan ~4-6 query setting & schedule), pemuatan `Habit::all()`, perulangan PHP untuk habit analytics, dan agregasi terpisah.\n";
        $markdown .= "- **Dashboard Admin**: Menjalankan query berulang `whereDoesntHave('roles')` dan agregasi bulanan dengan fungsi SQL raw `MONTH()`.\n";
        $markdown .= "- **Admin Laporan / Attendance**: Banyak query filter dan relasi belum ter-cache / query berulang.\n\n";
        $markdown .= "## 2. Frontend Build Artifacts (Baseline)\n\n";
        $markdown .= "| Asset / Chunk | Ukuran Asli | Gzip |\n";
        $markdown .= "| :--- | :---: | :---: |\n";
        $markdown .= "| `public/build/assets/app-IikcVV5G.js` | 412.92 kB | 142.84 kB |\n";
        $markdown .= "| `public/build/assets/app-DuQf7rDE.css` | 276.45 kB | 35.28 kB |\n";
        $markdown .= "| `public/build/assets/Index-Db1k3fZJ.js` | 193.28 kB | 65.52 kB |\n";
        $markdown .= "| `public/build/assets/dist-BR7cVwhK.js` | 193.15 kB | 67.06 kB |\n";
        $markdown .= "| `public/build/assets/leaflet-BGuttSqU.js` | 148.60 kB | 43.33 kB |\n";
        $markdown .= "| `public/build/assets/Index-BNM9lr_j.js` | 103.20 kB | 19.41 kB |\n";
        $markdown .= "| `public/build/assets/Hr-CAGV7Pb5.js` | 98.98 kB | 19.93 kB |\n";
        $markdown .= "| `public/build/assets/Index-CF--KyIM.js` | 96.56 kB | 22.03 kB |\n";
        $markdown .= "| `public/build/assets/Index-OnfHz3dc.js` | 91.63 kB | 20.94 kB |\n";
        $markdown .= "| `public/build/assets/AuthenticatedLayout-CyOLlCgL.js` | 79.26 kB | 19.25 kB |\n";
        $markdown .= "| `public/build/assets/FormHabit-kETI7Xmt.js` | 67.96 kB | 14.85 kB |\n";
        $markdown .= "| `public/build/assets/useDzikirSound-CbU30NVc.js` | 52.13 kB | 10.49 kB |\n";
        $markdown .= "| `public/build/assets/Dashboard-CGTsSrjV.js` | 41.27 kB | 10.47 kB |\n";
        $markdown .= "| `public/build/assets/IslamicWidget-BkLJuK-6.js` | 12.49 kB | 4.22 kB |\n\n";
        $markdown .= "## 3. Render-blocking Resource Baseline\n";
        $markdown .= "- `resources/views/app.blade.php`: Google Fonts stylesheet (`family=Amiri...&family=Amiri+Quran&family=Scheherazade+New`) dimuat di `<head>` untuk seluruh halaman aplikasi.\n";

        if (env('GENERATE_PERF_BASELINE') === 'true') {
            if (!file_exists(base_path('docs'))) {
                mkdir(base_path('docs'), 0755, true);
            }
            file_put_contents(base_path('docs/perf-baseline.md'), $markdown);
        }

        $this->assertTrue(true);
    }
}
