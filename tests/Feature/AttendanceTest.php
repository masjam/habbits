<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_attendance_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertStatus(200);
    }

    public function test_user_can_check_in()
    {
        $user = User::factory()->create();

        // Simulate request with dummy coordinates (Assuming coordinates inside the radius)
        $payload = [
            'latitude' => -7.7956,
            'longitude' => 110.3695,
            'late_reason' => 'Testing checkin',
            'photo' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAAoHBwgHBgoICAgLCgoLDhgQDg0NDh0VFhEYIx8lJCIfIiEmKzcvJik0KSEiMEExNDk7Pj4+JS5ESUM8SDc9Pjv/2wBDAQoLCw4NDhwQEBw7KCIoOzs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozv/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAD/xAAVAQEBAAAAAAAAAAAAAAAAAAAAAP/EABUQAQEAAAAAAAAAAAAAAAAAAAAA/8QAFREBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/wA=='
        ];

        $this->withoutExceptionHandling();
        
        $response = $this->actingAs($user)->post(route('attendance.check-in'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
        ]);
    }

    public function test_admin_can_store_leave_status_for_employee()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.attendance.store-leave'), [
            'user_id' => $employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'cuti',
            'notes' => 'Cuti tahunan yang disetujui',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'cuti',
            'approval_status' => 'approved',
            'approval_type' => 'cuti',
            'approved_by' => $admin->id,
            'notes' => 'Cuti tahunan yang disetujui',
        ]);
    }

    public function test_regular_user_cannot_store_leave_status()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $employee1 = User::factory()->create();
        $employee2 = User::factory()->create();

        $response = $this->actingAs($employee1)->post(route('admin.attendance.store-leave'), [
            'user_id' => $employee2->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'sakit',
        ]);

        // Role middleware redirects unauthorized users
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_employee_with_late_attendance_can_edit_late_reason_without_changing_times()
    {
        $employee = User::factory()->create();
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => now()->format('Y-m-d'),
            'time_in' => '07:45:00',
            'time_out' => '15:10:00',
            'status' => 'terlambat',
            'notes' => 'Presensi via Mesin RFID',
            'late_reason' => null,
        ]);

        // Kirim juga time_in / time_out / status palsu untuk memastikan server mengabaikannya
        $response = $this->actingAs($employee)->patch(route('attendance.update-late-reason', $attendance->id), [
            'late_reason' => 'Ban motor bocor di perjalanan',
            'time_in' => '06:50:00',
            'time_out' => '16:00:00',
            'status' => 'hadir',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $attendance->refresh();
        $this->assertEquals('Ban motor bocor di perjalanan', $attendance->late_reason);
        $this->assertEquals('07:45:00', $attendance->time_in);
        $this->assertEquals('15:10:00', $attendance->time_out);
        $this->assertEquals('terlambat', $attendance->status);
        $this->assertEquals('Presensi via Mesin RFID', $attendance->notes);
    }

    public function test_employee_cannot_edit_another_employees_late_reason()
    {
        $employee1 = User::factory()->create();
        $employee2 = User::factory()->create();
        $attendance = Attendance::create([
            'user_id' => $employee2->id,
            'date' => now()->format('Y-m-d'),
            'time_in' => '07:45:00',
            'status' => 'terlambat',
            'late_reason' => 'Alasan awal',
        ]);

        $response = $this->actingAs($employee1)->patch(route('attendance.update-late-reason', $attendance->id), [
            'late_reason' => 'Diubah orang lain',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Alasan awal', $attendance->fresh()->late_reason);
    }

    public function test_employee_cannot_edit_late_reason_if_status_is_not_terlambat()
    {
        $employee = User::factory()->create();
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => now()->format('Y-m-d'),
            'time_in' => '06:55:00',
            'status' => 'hadir',
            'late_reason' => null,
        ]);

        $response = $this->actingAs($employee)->patch(route('attendance.update-late-reason', $attendance->id), [
            'late_reason' => 'Mencoba isi alasan',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull($attendance->fresh()->late_reason);
    }
}
