<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 5：ステータス確認機能
 */
class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 15, 0));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_status_is_off_duty_when_not_clocked_in(): void
    {
        $this->actingAs($this->user)->get('/attendance')->assertSee('勤務外');
    }

    public function test_status_is_working_when_clocked_in(): void
    {
        AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-11-05',
            'clock_out' => null,
            'total_break_time' => null,
            'total_time' => null,
        ]);

        $this->actingAs($this->user)->get('/attendance')->assertSee('出勤中');
    }

    public function test_status_is_on_break_when_break_is_not_finished(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-11-05',
            'clock_out' => null,
            'total_break_time' => null,
            'total_time' => null,
        ]);
        AttendanceBreak::factory()->for($record)->create(['break_in' => '14:00:00', 'break_out' => null]);

        $this->actingAs($this->user)->get('/attendance')->assertSee('休憩中');
    }

    public function test_status_is_finished_when_clocked_out(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-05']);

        $this->actingAs($this->user)->get('/attendance')->assertSee('退勤済');
    }
}
