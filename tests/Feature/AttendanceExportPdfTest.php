<?php

namespace Tests\Feature;

use App\Models\SpecialSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceExportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser(): User
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        return $user;
    }

    public function test_admin_can_export_monthly_attendance_pdf(): void
    {
        $admin = $this->getAdminUser();

        SpecialSchedule::create([
            'name' => 'Jadwal Pesantren Kilat',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->startOfMonth()->addDays(3)->format('Y-m-d'),
            'time_in' => '07:30',
            'time_out' => '14:00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendance.export-pdf', [
            'type' => 'monthly',
            'month' => now()->format('Y-m'),
        ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admin_can_export_daily_attendance_pdf(): void
    {
        $admin = $this->getAdminUser();

        SpecialSchedule::create([
            'name' => 'Hari Khusus Ramadhan',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'time_in' => '07:30',
            'time_out' => '13:00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendance.export-pdf', [
            'type' => 'daily',
            'date' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
