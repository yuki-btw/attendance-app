<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 8：退勤機能
 */
class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clock_out_button_works(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 9, 0));
        $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);

        $this->get('/attendance')->assertSee('退勤');

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 18, 0));
        $this->post('/attendance', ['action' => 'clock_out']);

        $this->get('/attendance')
            ->assertSee('退勤済')
            ->assertSee('お疲れ様でした。');
    }

    public function test_clock_out_time_is_shown_in_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 9, 0));
        $this->actingAs($this->user)->post('/attendance', ['action' => 'clock_in']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 0));
        $this->post('/attendance', ['action' => 'break_in']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 13, 0));
        $this->post('/attendance', ['action' => 'break_out']);
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 18, 0));
        $this->post('/attendance', ['action' => 'clock_out']);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'clock_out' => '18:00:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
        ]);
        $this->get('/attendance/list')
            ->assertSeeInOrder(['11/05(木)', '09:00', '18:00', '1:00', '8:00']);
    }
}
