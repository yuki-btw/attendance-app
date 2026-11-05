<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 12：勤怠一覧情報取得機能（管理者）
 */
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 20, 0));
        $this->admin = User::factory()->admin()->create();
        $this->userA = User::factory()->create(['name' => '一般 一郎']);
        $this->userB = User::factory()->create(['name' => '一般 二郎']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_all_users_attendance_of_the_day_is_shown_correctly(): void
    {
        AttendanceRecord::factory()->for($this->userA)->create([
            'date' => '2026-11-05', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00',
            'total_break_time' => '01:00:00', 'total_time' => '08:00:00',
        ]);
        AttendanceRecord::factory()->for($this->userB)->create([
            'date' => '2026-11-05', 'clock_in' => '10:15:00', 'clock_out' => '19:30:00',
            'total_break_time' => '00:45:00', 'total_time' => '08:30:00',
        ]);
        AttendanceRecord::factory()->for($this->userA)->create(['date' => '2026-11-04', 'clock_in' => '07:05:00']);

        $response = $this->actingAs($this->admin)->get('/admin/attendance/list');

        $response->assertOk();
        $response->assertSeeInOrder(['一般 一郎', '09:00', '18:00', '1:00', '8:00']);
        $response->assertSeeInOrder(['一般 二郎', '10:15', '19:30', '0:45', '8:30']);
        $response->assertDontSee('07:05');
    }

    public function test_current_date_is_shown(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('2026年11月05日の勤怠')
            ->assertSee('2026/11/05');
    }

    public function test_previous_day_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->userA)->create(['date' => '2026-11-04', 'clock_in' => '08:20:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('?date=2026-11-04', false);

        $this->get('/admin/attendance/list?date=2026-11-04')
            ->assertSee('2026/11/04')
            ->assertSeeInOrder(['一般 一郎', '08:20']);
    }

    public function test_next_day_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->userB)->create(['date' => '2026-11-06', 'clock_in' => '09:40:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('?date=2026-11-06', false);

        $this->get('/admin/attendance/list?date=2026-11-06')
            ->assertSee('2026/11/06')
            ->assertSeeInOrder(['一般 二郎', '09:40']);
    }

    public function test_general_user_cannot_access_admin_pages(): void
    {
        $this->actingAs($this->userA)->get('/admin/attendance/list')->assertForbidden();
    }
}
