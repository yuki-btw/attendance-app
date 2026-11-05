<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 10：勤怠詳細情報取得機能（一般ユーザー）
 */
class UserAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '勤怠 花子']);
        $this->record = AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-11-04',
            'clock_in' => '08:45:00',
            'clock_out' => '17:50:00',
        ]);
        AttendanceBreak::factory()->for($this->record)->create(['break_in' => '12:00:00', 'break_out' => '12:45:00']);
        AttendanceBreak::factory()->for($this->record)->create(['break_in' => '15:00:00', 'break_out' => '15:15:00']);
    }

    public function test_name_is_login_user_name(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('value="勤怠 花子"', false);
    }

    public function test_date_is_selected_date(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="11月4日"', false);
    }

    public function test_clock_in_and_out_match_stamped_times(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('name="new_clock_in" value="08:45"', false)
            ->assertSee('name="new_clock_out" value="17:50"', false);
    }

    public function test_breaks_match_stamped_times(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('name="new_break_in[0]" value="12:00"', false)
            ->assertSee('name="new_break_out[0]" value="12:45"', false)
            ->assertSee('name="new_break_in[1]" value="15:00"', false)
            ->assertSee('name="new_break_out[1]" value="15:15"', false)
            ->assertSee('name="new_break_in[2]" value=""', false);
    }

    public function test_other_users_record_cannot_be_viewed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/attendance/'.$this->record->id)
            ->assertNotFound();
    }

    public function test_detail_path_is_also_available(): void
    {
        $this->actingAs($this->user)->get('/attendance/detail/'.$this->record->id)
            ->assertOk()
            ->assertSee('value="11月4日"', false);
    }
}
