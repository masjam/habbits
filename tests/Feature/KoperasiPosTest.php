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

class KoperasiPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $koperasiUser;
    protected User $regularUser;
    protected User $adminUser;
    protected User $superadminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'koperasi', 'guard_name' => 'web']);

        $this->superadminUser = User::factory()->create([
            'name'  => 'Super Admin Sekolah',
            'email' => 'superadmin@sdam.sch.id',
        ]);
        $this->superadminUser->assignRole('superadmin');

        $this->koperasiUser = User::factory()->create([
            'name'  => 'Petugas Kasir Koperasi',
            'email' => 'kasir@sdam.sch.id',
        ]);
        $this->koperasiUser->assignRole('koperasi');

        $this->adminUser = User::factory()->create([
            'name'  => 'Admin Sekolah',
            'email' => 'admin@sdam.sch.id',
        ]);
        $this->adminUser->assignRole('admin');

        $this->regularUser = User::factory()->create([
            'name'   => 'Ahmad Guru',
            'email'  => 'ahmad@sdam.sch.id',
            'nip'    => '199001012020011001',
            'divisi' => 'Guru Kelas',
        ]);
        $this->regularUser->assignRole('user');
    }

    public function test_koperasi_staff_and_superadmin_can_access_pos_system(): void
    {
        $responseKoperasi = $this->actingAs($this->koperasiUser)->get(route('koperasi.pos'));
        $responseKoperasi->assertStatus(200);

        $responseSuperadmin = $this->actingAs($this->superadminUser)->get(route('koperasi.pos'));
        $responseSuperadmin->assertStatus(200);
    }

    public function test_regular_admin_and_user_cannot_access_pos_system(): void
    {
        // Admin biasa tidak boleh mengakses POS (hanya petugas koperasi & superadmin)
        $responseAdmin = $this->actingAs($this->adminUser)->get(route('koperasi.pos'));
        $responseAdmin->assertRedirect(route('dashboard'));
        $responseAdmin->assertSessionHas('error');

        // User biasa tidak boleh mengakses POS
        $responseUser = $this->actingAs($this->regularUser)->get(route('koperasi.pos'));
        $responseUser->assertRedirect(route('dashboard'));
        $responseUser->assertSessionHas('error');
    }

    public function test_check_voucher_api_validates_voucher_properly(): void
    {
        $voucher = HabitVoucher::create([
            'user_id'             => $this->regularUser->id,
            'voucher_code'        => 'SDAM-VCR-TEST-1234',
            'period'              => Carbon::today()->format('Y-m'),
            'title'               => 'Voucher Habit Test',
            'reward_title'        => 'Voucher Belanja Koperasi',
            'reward_value'        => 'Voucher Belanja Rp 50.000',
            'target_percentage'   => 80.0,
            'achieved_percentage' => 88.5,
            'total_score'         => 885,
            'max_possible_score'  => 1000,
            'status'              => 'active',
            'valid_until'         => Carbon::today()->addDays(30),
        ]);

        // 1. Cek dengan kode voucher
        $response = $this->actingAs($this->koperasiUser)->postJson(route('koperasi.pos.check'), [
            'code' => 'SDAM-VCR-TEST-1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'valid',
            ])
            ->assertJsonPath('voucher.user.name', 'Ahmad Guru');

        // 2. Cek dengan NIP pegawai
        $responseNip = $this->actingAs($this->koperasiUser)->postJson(route('koperasi.pos.check'), [
            'code' => $this->regularUser->nip,
        ]);

        $responseNip->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'valid',
            ]);
    }

    public function test_pos_redeem_processes_checkout_and_financials(): void
    {
        $voucher = HabitVoucher::create([
            'user_id'             => $this->regularUser->id,
            'voucher_code'        => 'SDAM-VCR-POS-7788',
            'period'              => Carbon::today()->format('Y-m'),
            'title'               => 'Voucher Habit Test',
            'reward_title'        => 'Voucher Belanja Koperasi',
            'reward_value'        => 'Voucher Belanja Rp 50.000',
            'target_percentage'   => 80.0,
            'achieved_percentage' => 90.0,
            'total_score'         => 900,
            'max_possible_score'  => 1000,
            'status'              => 'active',
            'valid_until'         => Carbon::today()->addDays(30),
        ]);

        $response = $this->actingAs($this->koperasiUser)->post(route('koperasi.pos.redeem'), [
            'voucher_id'         => $voucher->id,
            'transaction_amount' => 65000,
            'paid_amount'        => 20000,
            'items_summary'      => 'Buku Tulis & ATK',
            'notes'              => 'Kasir POS Shift Pagi',
        ]);

        $response->assertRedirect(route('koperasi.pos'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('lastReceipt');

        $voucher->refresh();
        $this->assertEquals('claimed', $voucher->status);
        $this->assertEquals($this->koperasiUser->id, $voucher->redeemed_by);
        $this->assertNotNull($voucher->claimed_at);
        $this->assertNotNull($voucher->transaction_code);
        $this->assertEquals(65000, $voucher->transaction_amount);
        $this->assertEquals(50000, $voucher->discount_amount);
        $this->assertEquals(20000, $voucher->paid_amount);
        $this->assertEquals(5000, $voucher->change_amount);
        $this->assertEquals('Buku Tulis & ATK', $voucher->items_summary);
    }

    public function test_cancel_transaction_voids_redeemed_voucher(): void
    {
        $voucher = HabitVoucher::create([
            'user_id'             => $this->regularUser->id,
            'voucher_code'        => 'SDAM-VCR-VOID-9999',
            'period'              => Carbon::today()->format('Y-m'),
            'title'               => 'Voucher Habit Test',
            'reward_title'        => 'Voucher Belanja Koperasi',
            'reward_value'        => 'Voucher Belanja Rp 50.000',
            'status'              => 'claimed',
            'claimed_at'          => now(),
            'redeemed_by'         => $this->koperasiUser->id,
            'transaction_code'    => 'POS-KOP-202611-TEST',
            'transaction_amount'  => 50000,
            'discount_amount'     => 50000,
        ]);

        $response = $this->actingAs($this->koperasiUser)->post(route('koperasi.pos.cancel', $voucher->id));

        $response->assertSessionHas('success');

        $voucher->refresh();
        $this->assertEquals('active', $voucher->status);
        $this->assertNull($voucher->claimed_at);
        $this->assertNull($voucher->redeemed_by);
        $this->assertNull($voucher->transaction_code);
        $this->assertEquals(0, $voucher->transaction_amount);
    }

    public function test_employee_can_convert_voucher_to_rfid_balance(): void
    {
        $voucher = HabitVoucher::create([
            'user_id'             => $this->regularUser->id,
            'voucher_code'        => 'SDAM-VCR-RFID-1122',
            'period'              => Carbon::today()->format('Y-m'),
            'title'               => 'Voucher Habit Test',
            'reward_title'        => 'Voucher Belanja Koperasi',
            'reward_value'        => 'Voucher Belanja Rp 50.000',
            'status'              => 'active',
            'valid_until'         => Carbon::today()->addDays(30),
        ]);

        $this->assertEquals(0, $this->regularUser->coop_balance);

        $response = $this->actingAs($this->regularUser)->post(route('habit.vouchers.convert-to-rfid', $voucher->id));
        $response->assertSessionHas('success');

        $this->regularUser->refresh();
        $voucher->refresh();

        $this->assertEquals(50000, $this->regularUser->coop_balance);
        $this->assertEquals('claimed', $voucher->status);
    }

    public function test_pos_checkout_using_rfid_card_balance_deducts_balance(): void
    {
        // Berikan saldo awal RFID ke pegawai
        $this->regularUser->update(['coop_balance' => 100000]);

        $response = $this->actingAs($this->koperasiUser)->post(route('koperasi.pos.checkout'), [
            'items' => [
                ['name' => 'Buku Tulis SiDU', 'price' => 5000, 'qty' => 3, 'subtotal' => 15000],
                ['name' => 'Kopi Good Day', 'price' => 7000, 'qty' => 1, 'subtotal' => 7000],
            ],
            'total_amount'   => 22000,
            'payment_method' => 'rfid_card',
            'customer_id'    => $this->regularUser->id,
        ]);

        $response->assertRedirect(route('koperasi.pos'));
        $response->assertSessionHas('success');

        $this->regularUser->refresh();
        $this->assertEquals(78000, $this->regularUser->coop_balance); // 100000 - 22000 = 78000
    }

    public function test_koperasi_staff_login_redirects_directly_to_pos(): void
    {
        $koperasiOnlyUser = User::factory()->create([
            'email'    => 'murni.kasir@sdam.sch.id',
            'password' => bcrypt('password123'),
        ]);
        $koperasiOnlyUser->assignRole('koperasi');

        $response = $this->post(route('login.attempt'), [
            'email'    => 'murni.kasir@sdam.sch.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('koperasi.pos'));
    }

    public function test_export_monthly_sales_downloads_excel(): void
    {
        $response = $this->actingAs($this->koperasiUser)->get(route('koperasi.export.sales-monthly', [
            'period' => '2026-11',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('Laporan_Penjualan_Koperasi_SDAM_2026-11.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_export_stock_opname_downloads_excel(): void
    {
        $response = $this->actingAs($this->koperasiUser)->get(route('koperasi.export.stock-opname'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('Laporan_Stock_Opname_Koperasi_SDAM_', $response->headers->get('content-disposition'));
    }

    public function test_export_product_template_downloads_excel(): void
    {
        $response = $this->actingAs($this->koperasiUser)->get(route('koperasi.export.product-template'));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('Template_Katalog_Barang_Koperasi_SDAM.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_stock_opname_dashboard_accessible(): void
    {
        $response = $this->actingAs($this->koperasiUser)->get(route('koperasi.stock-opname'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Koperasi/POS/StockOpname')
            ->has('metrics')
            ->has('categoryBreakdown')
            ->has('products')
            ->has('recentLogs')
        );
    }

    public function test_single_stock_adjustment_updates_stock_and_creates_log(): void
    {
        $product = \App\Models\CoopProduct::create([
            'name'       => 'Seragam Batik SDAM L',
            'barcode'    => '89999901',
            'category'   => 'Seragam Siswa',
            'price'      => 75000,
            'cost_price' => 60000,
            'stock'      => 20,
            'unit'       => 'Stel',
        ]);

        $response = $this->actingAs($this->koperasiUser)->post(route('koperasi.stock-opname.adjust'), [
            'product_id'   => $product->id,
            'actual_stock' => 17,
            'type'         => 'damage',
            'notes'        => 'Ditemukan 3 stel robek',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals(17, $product->stock);

        $this->assertDatabaseHas('coop_stock_logs', [
            'coop_product_id' => $product->id,
            'type'            => 'damage',
            'stock_before'    => 20,
            'stock_after'     => 17,
            'difference'      => -3,
        ]);
    }

    public function test_quick_batch_opname_updates_multiple_products(): void
    {
        $p1 = \App\Models\CoopProduct::create([
            'name'       => 'Sabun Dettol 100g',
            'category'   => 'Sabun & Cuci',
            'price'      => 9000,
            'cost_price' => 7000,
            'stock'      => 50,
        ]);

        $p2 = \App\Models\CoopProduct::create([
            'name'       => 'Buku Kotak SDAM',
            'category'   => 'ATK & Buku',
            'price'      => 5000,
            'cost_price' => 3500,
            'stock'      => 100,
        ]);

        $response = $this->actingAs($this->koperasiUser)->post(route('koperasi.stock-opname.quick-batch'), [
            'items' => [
                ['product_id' => $p1->id, 'physical_stock' => 48],
                ['product_id' => $p2->id, 'physical_stock' => 105],
            ],
            'notes' => 'Audit mingguan etalase',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $p1->refresh();
        $p2->refresh();

        $this->assertEquals(48, $p1->stock);
        $this->assertEquals(105, $p2->stock);

        $this->assertDatabaseHas('coop_stock_logs', [
            'coop_product_id' => $p1->id,
            'difference'      => -2,
        ]);

        $this->assertDatabaseHas('coop_stock_logs', [
            'coop_product_id' => $p2->id,
            'difference'      => 5,
        ]);
    }
}
