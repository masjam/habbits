<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormHabitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_form_habit()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('habit.form'));

        $response->assertStatus(200);
    }

    public function test_user_can_submit_form_habit()
    {
        $user = User::factory()->create();
        
        $habit = Habit::create([
            'nama_habit' => 'Test Sholat Dhuha',
            'template' => 'dhuha',
            'skor_maksimal' => 2,
            'target_pencapaian' => 4,
            'satuan' => 'rakaat',
            'status_aktif' => true,
        ]);

        $payload = [
            'tanggal' => now()->toDateString(),
            'logs' => [
                [
                    'habit_id' => $habit->id,
                    'nilai_input' => 4,
                    'details' => [],
                ]
            ]
        ];

        $response = $this->actingAs($user)->post(route('habit.form.store'), $payload);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('habit_logs', [
            'user_id' => $user->id,
            'habit_id' => $habit->id,
            'nilai_input' => 4,
            'skor_diperoleh' => 2,
        ]);
    }
}
