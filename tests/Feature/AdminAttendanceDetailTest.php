<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * テストケースID 13：勤怠詳細情報取得・修正機能（管理者）
 */
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->record = AttendanceRecord::factory()
            ->for(User::factory()->create(['name' => 'スタッフ 太郎']))
            ->create(['date' => '2026-11-04', 'clock_in' => '09:10:00', 'clock_out' => '18:20:00', 'comment' => '通常勤務']);
        AttendanceBreak::factory()->for($this->record)->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00', ''],
            'new_break_out' => ['13:00', ''],
            'comment' => '打刻漏れのため修正',
        ], $overrides);
    }

    private function postUpdate(array $input): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/attendance/'.$this->record->id)
            ->post('/admin/attendance/'.$this->record->id, $input);
    }

    public function test_detail_shows_selected_attendance(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('value="スタッフ 太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月4日"', false)
            ->assertSee('value="09:10"', false)
            ->assertSee('value="18:20"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="13:00"', false)
            ->assertSee('通常勤務');
    }

    public function test_error_when_clock_in_is_after_clock_out(): void
    {
        $this->postUpdate($this->validInput(['new_clock_in' => '19:00']))
            ->assertRedirect('/admin/attendance/'.$this->record->id)
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_break_in_is_after_clock_out(): void
    {
        $this->postUpdate($this->validInput(['new_break_in' => ['18:30', ''], 'new_break_out' => ['18:45', '']]))
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_error_when_break_out_is_after_clock_out(): void
    {
        $this->postUpdate($this->validInput(['new_break_out' => ['18:30', '']]))
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_error_when_comment_is_empty(): void
    {
        $this->postUpdate($this->validInput(['comment' => '']))
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_admin_can_correct_attendance_directly(): void
    {
        $this->postUpdate($this->validInput([
            'new_break_in' => ['12:00', '15:00', ''],
            'new_break_out' => ['12:45', '15:15', ''],
        ]))->assertRedirect('/admin/attendance/'.$this->record->id);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $this->record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
            'comment' => '打刻漏れのため修正',
        ]);
        $this->assertDatabaseCount('breaks', 2);
        $this->assertDatabaseCount('applications', 0);
    }
}
