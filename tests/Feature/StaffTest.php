<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 14：ユーザー情報取得機能（管理者）
 */
class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 20, 10, 0));
        $this->admin = User::factory()->admin()->create(['name' => '管理 者', 'email' => 'admin@example.com']);
        $this->staff = User::factory()->create(['name' => 'スタッフ 一', 'email' => 'staff1@example.com']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_see_all_general_users_name_and_email(): void
    {
        User::factory()->create(['name' => 'スタッフ 二', 'email' => 'staff2@example.com']);

        $this->actingAs($this->admin)->get('/admin/staff/list')
            ->assertOk()
            ->assertSeeInOrder(['スタッフ 一', 'staff1@example.com'])
            ->assertSeeInOrder(['スタッフ 二', 'staff2@example.com'])
            ->assertDontSee('admin@example.com');
    }

    public function test_staff_attendance_is_shown_correctly(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create([
            'date' => '2026-11-02', 'clock_in' => '09:05:00', 'clock_out' => '18:10:00',
            'total_break_time' => '01:00:00', 'total_time' => '08:05:00',
        ]);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertOk()
            ->assertSee('スタッフ 一さんの勤怠')
            ->assertSee('2026/11')
            ->assertSeeInOrder(['11/02(月)', '09:05', '18:10', '1:00', '8:05']);
    }

    public function test_previous_month_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-10-01', 'clock_in' => '08:55:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('?date=2026-10', false);

        $this->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-10')
            ->assertSee('2026/10')
            ->assertSeeInOrder(['10/01(木)', '08:55']);
    }

    public function test_next_month_is_shown(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-12-01', 'clock_in' => '09:25:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('?date=2026-12', false);

        $this->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-12')
            ->assertSee('2026/12')
            ->assertSeeInOrder(['12/01(火)', '09:25']);
    }

    public function test_detail_link_moves_to_attendance_detail(): void
    {
        $record = AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-10']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee(url('/attendance/'.$record->id));

        $this->get('/attendance/'.$record->id)->assertRedirect('/admin/attendance/'.$record->id);

        $this->get('/admin/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('value="11月10日"', false);
    }
}
