<?php

namespace Tests\Feature;

use App\Models\SpecialSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser(): User
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        return $user;
    }

    public function test_can_view_special_schedules(): void
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('admin.special-schedules.index'));
        $response->assertStatus(200);
    }

    public function test_can_create_special_schedule(): void
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->post(route('admin.special-schedules.store'), [
            'name' => 'Jadwal Ujian Semester',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'time_in' => '07:30',
            'time_out' => '13:00',
            'late_tolerance' => 15,
            'latitude' => '-7.7956',
            'longitude' => '110.3695',
            'radius' => 100,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('special_schedules', [
            'name' => 'Jadwal Ujian Semester',
        ]);
    }

    public function test_can_update_special_schedule_with_seconds_or_minutes(): void
    {
        $user = $this->getAdminUser();
        $schedule = SpecialSchedule::create([
            'name' => 'Original Name',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'time_in' => '07:30:00',
            'time_out' => '13:00:00',
            'late_tolerance' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('admin.special-schedules.update', $schedule->id), [
            'name' => 'Updated Special Name',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-06',
            'time_in' => '08:00:00', // Sent with seconds
            'time_out' => '12:30:00', // Sent with seconds
            'late_tolerance' => 10,
            'latitude' => null,
            'longitude' => null,
            'radius' => 150,
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('special_schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Special Name',
        ]);
        $this->assertFalse((bool) $schedule->fresh()->is_active);
    }

    public function test_can_delete_special_schedule(): void
    {
        $user = $this->getAdminUser();
        $schedule = SpecialSchedule::create([
            'name' => 'To Delete',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'time_in' => '07:30',
            'time_out' => '13:00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.special-schedules.destroy', $schedule->id));
        $response->assertRedirect();
        $this->assertDatabaseMissing('special_schedules', [
            'id' => $schedule->id,
        ]);
    }
}
