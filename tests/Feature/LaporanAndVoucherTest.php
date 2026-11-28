<?php

namespace Tests\Feature;

use App\Jobs\ProcessLaporanExport;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\HabitVoucher;
use App\Models\HabitVoucherLog;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\HabitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanAndVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $pegawaiA;
    protected User $pegawaiB;
    protected Habit $habit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            HabitSeeder::class,
        ]);

        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'monthly_target_score'], ['value' => '80']);
        Setting::updateOrCreate(['key' => 'voucher_min_percentage'], ['value' => '80']);
        Setting::updateOrCreate(['key' => 'voucher_reward_amount'], ['value' => '50000']);
        Setting::updateOrCreate(['key' => 'voucher_expiry_days'], ['value' => '30']);

        $this->admin = User::factory()->create([
            'name' => 'Admin Laporan',
            'email' => 'admin.laporan@example.com',
            'gender' => 'L',
            'divisi' => 'Manajemen',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole('admin');

        $this->pegawaiA = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'gender' => 'L',
            'divisi' => 'IT',
            'password' => Hash::make('password'),
        ]);
        $this->pegawaiA->assignRole('user');

        $this->pegawaiB = User::factory()->create([
            'name' => 'Siti Aminah',
            'email' => 'siti.aminah@example.com',
            'gender' => 'P',
            'divisi' => 'Keuangan',
            'password' => Hash::make('password'),
        ]);
        $this->pegawaiB->assignRole('user');

        $this->habit = Habit::first();
    }

    public function test_non_admin_cannot_access_laporan_or_vouchers(): void
    {
        $this->actingAs($this->pegawaiA)
            ->get(route('admin.laporan'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->pegawaiA)
            ->getJson(route('admin.laporan'))
            ->assertStatus(403);

        $this->actingAs($this->pegawaiA)
            ->get(route('admin.vouchers.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->pegawaiA)
            ->postJson(route('admin.vouchers.generate'), [])
            ->assertStatus(403);
    }

    public function test_laporan_filters_and_validation_and_query_count(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->format('Y-m-d');

        // Give Budi a high score and Siti a low score
        HabitLog::create([
            'user_id' => $this->pegawaiA->id,
            'habit_id' => $this->habit->id,
            'tanggal' => $today,
            'skor_diperoleh' => 2500,
        ]);

        HabitLog::create([
            'user_id' => $this->pegawaiB->id,
            'habit_id' => $this->habit->id,
            'tanggal' => $today,
            'skor_diperoleh' => 10,
        ]);

        // Invalid filter validation
        $this->actingAs($this->admin)
            ->get(route('admin.laporan', ['month' => 99, 'per_page' => 999]))
            ->assertSessionHasErrors(['month', 'per_page']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Filter by divisi & search
        $response = $this->actingAs($this->admin)
            ->get(route('admin.laporan', [
                'month' => $now->month,
                'year' => $now->year,
                'divisi' => 'IT',
                'search' => 'Budi',
                'kategori_skor' => 'tercapai',
            ]));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Laporan/Index')
                ->where('filters.divisi', 'IT')
                ->where('filters.search', 'Budi')
                ->where('filters.kategori_skor', 'tercapai')
                ->has('leaderboard.data', 1)
                ->where('leaderboard.data.0.name', 'Budi Santoso')
            );

        $this->assertLessThan(25, count($queries), 'Query count for /admin/laporan exceeded 25');
    }

    public function test_laporan_export_dispatches_job_with_matching_filters(): void
    {
        Queue::fake();
        $now = Carbon::now('Asia/Jakarta');

        $this->actingAs($this->admin)
            ->get(route('admin.laporan.export', [
                'type' => 'excel',
                'month' => $now->month,
                'year' => $now->year,
                'divisi' => 'IT',
                'search' => 'Budi',
                'kategori_skor' => 'tercapai',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ProcessLaporanExport::class, function (ProcessLaporanExport $job) use ($now) {
            return $job->userToNotify->id === $this->admin->id
                && $job->type === 'excel'
                && (int) $job->month === $now->month
                && (int) $job->year === $now->year
                && $job->divisi === 'IT'
                && $job->search === 'Budi'
                && $job->kategori_skor === 'tercapai';
        });
    }

    public function test_voucher_generate_idempotent_redeem_expiry_and_unredeem_guards(): void
    {
        $period = Carbon::now('Asia/Jakarta')->format('Y-m');
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        // Qualify Budi for voucher (>= 80% of target)
        HabitLog::create([
            'user_id' => $this->pegawaiA->id,
            'habit_id' => $this->habit->id,
            'tanggal' => $today,
            'skor_diperoleh' => 5000,
        ]);

        // Generate voucher
        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.generate'), [
                'period' => $period,
                'user_id' => $this->pegawaiA->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $voucher = HabitVoucher::where('user_id', $this->pegawaiA->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($voucher);
        $this->assertSame('active', $voucher->status);
        $this->assertDatabaseHas('habit_voucher_logs', [
            'habit_voucher_id' => $voucher->id,
            'action' => 'generated',
            'actor_id' => $this->admin->id,
        ]);

        // Redeem once -> success
        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.redeem'), [
                'voucher_id' => $voucher->id,
                'notes' => 'Ditukar di kasir',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $voucher->refresh();
        $this->assertSame('claimed', $voucher->status);
        $this->assertSame(1, HabitVoucherLog::where('habit_voucher_id', $voucher->id)->where('action', 'redeemed')->count());

        // Second redeem (double-submit) -> warning, no duplicate redeemed log
        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.redeem'), [
                'voucher_code' => $voucher->voucher_code,
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(1, HabitVoucherLog::where('habit_voucher_id', $voucher->id)->where('action', 'redeemed')->count());

        // Unredeem blocked when transaction_code is set
        $voucher->update(['transaction_code' => 'TRX-POS-001']);

        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.unredeem', $voucher))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('claimed', $voucher->fresh()->status);

        // Unredeem succeeds when no transaction_code is set
        $voucher->update(['transaction_code' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.unredeem', $voucher), [
                'notes' => 'Salah klik',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('active', $voucher->fresh()->status);
        $this->assertDatabaseHas('habit_voucher_logs', [
            'habit_voucher_id' => $voucher->id,
            'action' => 'unredeemed',
            'actor_id' => $this->admin->id,
        ]);

        // Expired voucher cannot be redeemed
        $voucher->update([
            'valid_until' => Carbon::now('Asia/Jakarta')->subDay(),
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.vouchers.redeem'), [
                'voucher_id' => $voucher->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('active', $voucher->fresh()->status);

        // Query count check on /admin/vouchers index
        DB::flushQueryLog();
        DB::enableQueryLog();

        $indexResp = $this->actingAs($this->admin)
            ->get(route('admin.vouchers.index', ['period' => $period]));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $indexResp->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Vouchers/Index')
                ->where('period', $period)
                ->has('reportData.data')
            );

        $this->assertLessThan(25, count($queries), 'Query count for /admin/vouchers exceeded 25');
    }
}
