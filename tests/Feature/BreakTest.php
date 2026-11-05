<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 7：休憩機能
 */
class BreakTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 0));
        $this->user = User::factory()->create();
        AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-11-05',
            'clock_in' => '09:00:00',
            'clock_out' => null,
            'total_break_time' => null,
            'total_time' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_break_in_button_works(): void
    {
        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('出勤中')
            ->assertSee('休憩入');

        $this->post('/attendance', ['action' => 'break_in']);

        $this->get('/attendance')->assertSee('休憩中');
    }

    public function test_break_can_be_taken_many_times_a_day(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 30));
        $this->post('/attendance', ['action' => 'break_out']);

        $this->get('/attendance')->assertSee('休憩入');
    }

    public function test_break_out_button_works(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);

        $this->get('/attendance')->assertSee('休憩戻');

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 30));
        $this->post('/attendance', ['action' => 'break_out']);

        $this->get('/attendance')->assertSee('出勤中');
    }

    public function test_break_out_can_be_done_many_times_a_day(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 30));
        $this->post('/attendance', ['action' => 'break_out']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 15, 0));
        $this->post('/attendance', ['action' => 'break_in']);

        $this->get('/attendance')->assertSee('休憩戻');
        $this->assertDatabaseCount('breaks', 2);
    }

    public function test_break_time_is_shown_in_attendance_list(): void
    {
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 30));
        $this->post('/attendance', ['action' => 'break_out']);

        $this->assertDatabaseHas('breaks', ['break_in' => '12:00:00', 'break_out' => '12:30:00']);
        $this->get('/attendance/list')
            ->assertSeeInOrder(['11/05(木)', '09:00', '0:30']);
    }
}
