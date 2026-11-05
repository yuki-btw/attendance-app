<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 15：勤怠情報修正機能（管理者）
 */
class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->userA = User::factory()->create(['name' => '申請 一郎']);
        $this->userB = User::factory()->create(['name' => '申請 二郎']);
    }

    private function createCorrectionRequest(User $user, string $date, string $comment, bool $approved = false): Application
    {
        $record = AttendanceRecord::factory()->for($user)->create(['date' => $date]);
        $factory = Application::factory()->for($record)->state(['comment' => $comment]);

        return ($approved ? $factory->approved() : $factory)->create();
    }

    public function test_all_pending_requests_are_shown(): void
    {
        $this->createCorrectionRequest($this->userA, '2026-11-02', '一郎の承認待ち');
        $this->createCorrectionRequest($this->userB, '2026-11-03', '二郎の承認待ち');
        $this->createCorrectionRequest($this->userB, '2026-11-04', '二郎の承認済み', true);

        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSeeInOrder(['承認待ち', '申請 一郎', '2026/11/02', '一郎の承認待ち']);
        $response->assertSeeInOrder(['承認待ち', '申請 二郎', '2026/11/03', '二郎の承認待ち']);
        $response->assertSeeInOrder(['id="content1"', '一郎の承認待ち', '二郎の承認待ち', 'id="content2"'], false);
    }

    public function test_all_approved_requests_are_shown(): void
    {
        $this->createCorrectionRequest($this->userA, '2026-11-02', '一郎の承認済み', true);
        $this->createCorrectionRequest($this->userB, '2026-11-03', '二郎の承認済み', true);
        $this->createCorrectionRequest($this->userB, '2026-11-04', '二郎の承認待ち');

        $response = $this->actingAs($this->admin)->get('/stamp_correction_request/list');

        $response->assertSeeInOrder(['承認済み', '申請 一郎', '2026/11/02', '一郎の承認済み']);
        $response->assertSeeInOrder(['承認済み', '申請 二郎', '2026/11/03', '二郎の承認済み']);
        $response->assertSeeInOrder(['id="content2"', '一郎の承認済み'], false);
    }

    public function test_request_detail_is_shown_correctly(): void
    {
        $application = $this->createCorrectionRequest($this->userA, '2026-11-02', '寝坊のため');
        ProposalBreak::factory()->for($application)->create(['break_in' => '12:15:00', 'break_out' => '13:05:00']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="申請 一郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月2日"', false)
            ->assertSee('value="10:00"', false)
            ->assertSee('value="19:00"', false)
            ->assertSee('value="12:15"', false)
            ->assertSee('value="13:05"', false)
            ->assertSee('寝坊のため')
            ->assertSee('承認</button>', false);
    }

    public function test_request_is_approved_and_attendance_is_updated(): void
    {
        $application = $this->createCorrectionRequest($this->userA, '2026-11-02', '打刻修正');
        AttendanceBreak::factory()->for($application->attendanceRecord)->create();
        ProposalBreak::factory()->for($application)->create(['break_in' => '12:30:00', 'break_out' => '13:30:00']);
        ProposalBreak::factory()->for($application)->create(['break_in' => '16:00:00', 'break_out' => '16:30:00']);

        $this->actingAs($this->admin)
            ->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/approve/'.$application->id);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'approval_status' => '承認済み']);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $application->attendance_record_id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'total_break_time' => '01:30:00',
            'total_time' => '07:30:00',
            'comment' => '打刻修正',
        ]);
        $this->assertDatabaseCount('breaks', 2);
        $this->assertDatabaseHas('breaks', ['break_in' => '16:00:00', 'break_out' => '16:30:00']);

        $this->get('/stamp_correction_request/approve/'.$application->id)->assertSee('承認済み');
        $this->actingAs($this->userA)->get('/attendance/'.$application->attendance_record_id)
            ->assertSee('value="10:00"', false)
            ->assertSee('value="16:30"', false);
    }

    public function test_admin_post_to_attendance_detail_updates_directly(): void
    {
        $record = AttendanceRecord::factory()->for($this->userA)->create(['date' => '2026-11-02']);

        $this->actingAs($this->admin)->post('/attendance/'.$record->id, [
            'new_clock_in' => '08:00',
            'new_clock_out' => '17:00',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '管理者修正',
        ])->assertRedirect('/admin/attendance/'.$record->id);

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_in' => '08:00:00', 'total_time' => '09:00:00']);
        $this->assertDatabaseCount('applications', 0);
    }
}
