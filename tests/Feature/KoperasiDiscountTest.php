<?php

namespace Tests\Feature;

use App\Models\CoopDiscount;
use App\Models\CoopMember;
use App\Models\CoopProduct;
use App\Models\CoopTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KoperasiDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'koperasi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_non_koperasi_user_cannot_manage_discounts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('koperasi.discounts'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_koperasi_staff_can_create_toggle_and_delete_discount(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('koperasi');

        $this->actingAs($staff)
            ->post(route('koperasi.discounts.store'), [
                'name'             => 'Promo Milad',
                'discount_type'    => 'percentage',
                'discount_value'   => 20,
                'max_discount'     => 10000,
                'min_purchase'     => 10000,
                'member_type'      => 'pegawai',
                'product_category' => 'Alat Tulis',
                'start_date'       => Carbon::today()->toDateString(),
                'end_date'         => Carbon::today()->addDays(3)->toDateString(),
                'is_active'        => true,
            ])
            ->assertRedirect();

        $discount = CoopDiscount::where('name', 'Promo Milad')->firstOrFail();
        $this->assertTrue($discount->is_active);

        $this->actingAs($staff)
            ->patch(route('koperasi.discounts.toggle', $discount))
            ->assertRedirect();

        $this->assertFalse($discount->fresh()->is_active);

        $this->actingAs($staff)
            ->delete(route('koperasi.discounts.destroy', $discount))
            ->assertRedirect();

        $this->assertDatabaseMissing('coop_discounts', ['id' => $discount->id]);
    }

    public function test_checkout_applies_event_member_and_category_discount_server_side(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('koperasi');

        $member = CoopMember::create([
            'member_code'  => 'MBR-001',
            'name'         => 'Ananda Siswa',
            'type'         => 'siswa',
            'coop_balance' => 100000,
            'is_active'    => true,
        ]);

        $atk = CoopProduct::create([
            'name'      => 'Buku Tulis',
            'category'  => 'Alat Tulis',
            'price'     => 10000,
            'cost_price'=> 7000,
            'stock'     => 20,
            'is_active' => true,
        ]);

        $snack = CoopProduct::create([
            'name'      => 'Roti',
            'category'  => 'Makanan',
            'price'     => 5000,
            'cost_price'=> 3000,
            'stock'     => 20,
            'is_active' => true,
        ]);

        // Diskon 20% khusus siswa untuk kategori Alat Tulis hari ini (maks 5000)
        $activeDiscount = CoopDiscount::create([
            'name'             => 'Diskon Siswa ATK',
            'discount_type'    => 'percentage',
            'discount_value'   => 20,
            'max_discount'     => 5000,
            'min_purchase'     => 5000,
            'member_type'      => 'siswa',
            'product_category' => 'Alat Tulis',
            'start_date'       => Carbon::today()->toDateString(),
            'end_date'         => Carbon::today()->toDateString(),
            'is_active'        => true,
        ]);

        // Diskon kadaluarsa (kemarin) tidak boleh terpakai meski nilainya lebih besar
        CoopDiscount::create([
            'name'           => 'Promo Kemarin',
            'discount_type'  => 'fixed',
            'discount_value' => 15000,
            'start_date'     => Carbon::today()->subDays(5)->toDateString(),
            'end_date'       => Carbon::today()->subDay()->toDateString(),
            'is_active'      => true,
        ]);

        // Belanja 2x Buku Tulis (20.000) + 1x Roti (5.000) = 25.000
        // 20% dari 20.000 = 4.000 (di bawah cap 5.000), Roti tidak kena diskon
        $this->actingAs($staff)
            ->post(route('koperasi.pos.checkout'), [
                'items' => [
                    ['id' => $atk->id, 'name' => $atk->name, 'price' => 10000, 'qty' => 2, 'subtotal' => 20000],
                    ['id' => $snack->id, 'name' => $snack->name, 'price' => 5000, 'qty' => 1, 'subtotal' => 5000],
                ],
                'payment_method' => 'cash',
                'customer_type'  => 'member',
                'customer_id'    => $member->id,
                'cash_paid'      => 21000,
            ])
            ->assertRedirect(route('koperasi.pos'));

        $trx = CoopTransaction::latest('id')->firstOrFail();
        $this->assertEquals($activeDiscount->id, $trx->discount_id);
        $this->assertEquals('Diskon Siswa ATK', $trx->discount_name);
        $this->assertEquals(4000.0, (float) $trx->discount_amount);
        $this->assertEquals(25000.0, (float) $trx->total_amount);
        $this->assertEquals(21000.0, (float) $trx->cash_paid_amount);
        $this->assertEquals(0.0, (float) $trx->change_amount);
    }

    public function test_checkout_can_skip_auto_discount(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('koperasi');

        $snack = CoopProduct::create([
            'name'      => 'Air Mineral',
            'category'  => 'Minuman',
            'price'     => 4000,
            'cost_price'=> 2500,
            'stock'     => 10,
            'is_active' => true,
        ]);

        CoopDiscount::create([
            'name'           => 'Promo Umum',
            'discount_type'  => 'fixed',
            'discount_value' => 1000,
            'is_active'      => true,
        ]);

        $this->actingAs($staff)
            ->post(route('koperasi.pos.checkout'), [
                'items' => [
                    ['id' => $snack->id, 'name' => $snack->name, 'price' => 4000, 'qty' => 1, 'subtotal' => 4000],
                ],
                'payment_method' => 'cash',
                'skip_discount'  => true,
                'cash_paid'      => 4000,
            ])
            ->assertRedirect(route('koperasi.pos'));

        $trx = CoopTransaction::latest('id')->firstOrFail();
        $this->assertNull($trx->discount_id);
        $this->assertEquals(0.0, (float) $trx->discount_amount);
    }
}
