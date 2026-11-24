<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\HabitVoucher;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HabitVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_user_can_view_habit_vouchers_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('habit.vouchers'));
        $response->assertStatus(200);
    }

    public function test_target_achievement_reads_admin_setting(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Admin set target ke 85%
        Setting::updateOrCreate(
            ['key' => 'monthly_target_score'],
            ['value' => '85']
        );

        $target = HabitVoucher::getEffectiveTargetForUser($user);
        $this->assertEquals(85.0, $target);

        // Ubah target admin ke 75%
        Setting::updateOrCreate(
            ['key' => 'monthly_target_score'],
            ['value' => '75']
        );

        $target = HabitVoucher::getEffectiveTargetForUser($user);
        $this->assertEquals(75.0, $target);
    }

    public function test_voucher_is_generated_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        Setting::updateOrCreate(
            ['key' => 'monthly_target_score'],
            ['value' => '80']
        );

        $habit = Habit::create([
            'nama_habit' => 'Sholat Berjamaah',
            'deskripsi' => 'Sholat berjamaah 5 waktu',
            'skor_maksimal' => 10,
            'is_active' => true,
            'is_pengganti_haid' => false,
        ]);

        $today = Carbon::today();
        $daysInMonth = $today->daysInMonth;
        $totalScoresToAchieve = (int) round(0.9 * 10 * $daysInMonth);
        
        // Simpan log habit agar user mencapai 90%
        HabitLog::create([
            'user_id' => $user->id,
            'habit_id' => $habit->id,
            'tanggal' => $today->format('Y-m-d'),
            'nilai_input' => 1,
            'skor_diperoleh' => $totalScoresToAchieve,
        ]);

        // User membuka halaman voucher: voucher belum terbit karena belum di-generate admin
        $this->actingAs($user)->get(route('habit.vouchers'));
        $this->assertDatabaseMissing('habit_vouchers', [
            'user_id' => $user->id,
            'period'  => $today->format('Y-m'),
        ]);

        // Admin generate voucher untuk user
        $response = $this->actingAs($admin)->post(route('admin.vouchers.generate'), [
            'period'  => $today->format('Y-m'),
            'user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('habit_vouchers', [
            'user_id'      => $user->id,
            'period'       => $today->format('Y-m'),
            'status'       => 'active',
            'generated_by' => $admin->id,
        ]);
    }

    public function test_admin_or_koperasi_can_redeem_voucher(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $voucher = HabitVoucher::create([
            'user_id' => $user->id,
            'voucher_code' => 'SDAM-VCR-202611-TEST01',
            'period' => '2026-11',
            'title' => 'Voucher Apresiasi Habit November 2026',
            'reward_title' => 'Reward Apresiasi Habit',
            'reward_value' => 'Voucher Belanja Koperasi Rp 50.000',
            'target_percentage' => 80.0,
            'achieved_percentage' => 88.5,
            'status' => 'active',
            'valid_until' => Carbon::today()->addDays(30)->format('Y-m-d'),
        ]);

        // Verifikasi penukaran di koperasi
        $response = $this->actingAs($admin)->post(route('admin.vouchers.redeem'), [
            'voucher_id' => $voucher->id,
            'notes'      => 'Ditukarkan di Koperasi Sekolah',
        ]);

        $response->assertRedirect();
        $voucher->refresh();
        $this->assertEquals('claimed', $voucher->status);
        $this->assertTrue($voucher->isRedeemed());
        $this->assertNotNull($voucher->claimed_at);
        $this->assertEquals($admin->id, $voucher->redeemed_by);

        // Batalkan penukaran (unredeem)
        $unredeemResponse = $this->actingAs($admin)->post(route('admin.vouchers.unredeem', $voucher->id));
        $unredeemResponse->assertRedirect();
        $voucher->refresh();
        $this->assertEquals('active', $voucher->status);
        $this->assertFalse($voucher->isRedeemed());
        $this->assertNull($voucher->claimed_at);
    }
}
