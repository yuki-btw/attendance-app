<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケースID 11：勤怠詳細情報修正機能（一般ユーザー）
 */
class UserCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0));
        $this->user = User::factory()->create(['name' => '申請 太郎']);
        $this->record = AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-11-04',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        AttendanceBreak::factory()->for($this->record)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    private function postCorrection(array $input): TestResponse
    {
        return $this->actingAs($this->user)
            ->from('/attendance/'.$this->record->id)
            ->post('/attendance/'.$this->record->id, $input);
    }

    public function test_error_when_clock_in_is_after_clock_out(): void
    {
        $this->postCorrection($this->validInput(['new_clock_in' => '19:00']))
            ->assertRedirect('/attendance/'.$this->record->id)
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);

        $this->get('/attendance/'.$this->record->id)
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    public function test_error_when_break_in_is_after_clock_out(): void
    {
        $this->postCorrection($this->validInput(['new_break_in' => ['19:00', ''], 'new_break_out' => ['19:15', '']]))
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_error_when_break_out_is_after_clock_out(): void
    {
        $this->postCorrection($this->validInput(['new_break_out' => ['19:00', '']]))
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_comment_is_empty(): void
    {
        $this->postCorrection($this->validInput(['comment' => '']))
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_correction_request_is_shown_to_admin(): void
    {
        $this->postCorrection($this->validInput())->assertSessionHasNoErrors();

        $application = Application::first();
        $this->assertSame(Application::STATUS_PENDING, $application->approval_status);
        $this->assertDatabaseHas('proposal_breaks', ['application_id' => $application->id, 'break_in' => '12:00:00', 'break_out' => '13:00:00']);
        $this->assertDatabaseCount('proposal_breaks', 1);
        $this->assertDatabaseHas('attendance_records', ['id' => $this->record->id, 'clock_in' => '09:00:00']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['承認待ち', '申請 太郎', '2026/11/04', '電車遅延のため', '2026/11/05']);
        $this->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="申請 太郎"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('電車遅延のため');
    }

    public function test_all_own_pending_requests_are_shown(): void
    {
        $otherRecord = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-03']);
        $this->postCorrection($this->validInput(['comment' => '1件目の申請']));
        $this->actingAs($this->user)->post('/attendance/'.$otherRecord->id, $this->validInput(['comment' => '2件目の申請']));

        $response = $this->get('/stamp_correction_request/list');

        $response->assertSeeInOrder(['承認待ち', '申請 太郎', '2026/11/04', '1件目の申請']);
        $response->assertSeeInOrder(['承認待ち', '申請 太郎', '2026/11/03', '2件目の申請']);
    }

    public function test_approved_requests_are_shown(): void
    {
        $this->postCorrection($this->validInput(['comment' => '承認される申請']));
        $application = Application::first();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/stamp_correction_request/approve/'.$application->id);

        $this->actingAs($this->user)->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['承認済み', '申請 太郎', '2026/11/04', '承認される申請']);
    }

    public function test_detail_link_moves_to_attendance_detail(): void
    {
        $this->postCorrection($this->validInput());
        $application = Application::first();

        $this->get('/stamp_correction_request/list')
            ->assertSee(url('/application/'.$application->id));

        $this->get('/application/'.$application->id)
            ->assertOk()
            ->assertSee('value="11月4日"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('承認待ちのため修正できません');
    }

    public function test_detail_is_read_only_while_pending(): void
    {
        $this->postCorrection($this->validInput());

        $this->get('/attendance/'.$this->record->id)
            ->assertSee('承認待ちのため修正できません')
            ->assertDontSee('<button class="form__button--submit" type="submit">修正</button>', false);

        $this->postCorrection($this->validInput(['comment' => '二重申請']));
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_empty_break_rows_are_not_saved(): void
    {
        $this->postCorrection($this->validInput([
            'new_break_in' => ['12:00', '', ''],
            'new_break_out' => ['13:00', '', ''],
        ]));

        $this->assertDatabaseCount('proposal_breaks', 1);
    }
}
