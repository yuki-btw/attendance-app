<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 9：勤怠一覧情報取得機能（一般ユーザー）
 */
class UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 20, 10, 0));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_all_own_attendance_records_are_shown(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-02', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-03', 'clock_in' => '08:30:00', 'clock_out' => '17:45:00']);
        AttendanceRecord::factory()->create(['date' => '2026-11-04', 'clock_in' => '07:15:00', 'clock_out' => '16:15:00']);

        $response = $this->actingAs($this->user)->get('/attendance/list');

        $response->assertOk();
        $response->assertSeeInOrder(['11/02(月)', '09:00', '18:00', '1:00', '8:00']);
        $response->assertSeeInOrder(['11/03(火)', '08:30', '17:45']);
        $response->assertDontSee('07:15');
        $response->assertSee('11/30(月)');
    }

    public function test_current_month_is_shown(): void
    {
        $this->actingAs($this->user)->get('/attendance/list')->assertSee('2026/11');
    }

    public function test_previous_month_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-15', 'clock_in' => '09:05:00']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('?date=2026-10', false);

        $this->get('/attendance/list?date=2026-10')
            ->assertSee('2026/10')
            ->assertSeeInOrder(['10/15(木)', '09:05']);
    }

    public function test_next_month_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-12-01', 'clock_in' => '09:10:00']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('?date=2026-12', false);

        $this->get('/attendance/list?date=2026-12')
            ->assertSee('2026/12')
            ->assertSeeInOrder(['12/01(火)', '09:10']);
    }

    public function test_detail_link_moves_to_attendance_detail(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-10']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee(url('/attendance/'.$record->id));

        $this->get('/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('11月10日');
    }
}
