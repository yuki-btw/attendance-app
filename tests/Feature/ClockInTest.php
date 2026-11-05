<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 6：出勤機能
 */
class ClockInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 9, 0));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clock_in_button_works(): void
    {
        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('勤務外')
            ->assertSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);

        $this->post('/attendance', ['action' => 'clock_in'])->assertRedirect('/attendance');

        $this->get('/attendance')->assertSee('出勤中');
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'date' => '2026-11-05',
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_clock_in_is_allowed_only_once_a_day(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-05']);

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('退勤済')
            ->assertDontSee('value="clock_in"', false);

        $this->post('/attendance', ['action' => 'clock_in']);
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_clock_in_time_is_shown_in_attendance_list(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);

        $this->get('/attendance/list')
            ->assertSeeInOrder(['11/05(木)', '09:00']);
    }
}
