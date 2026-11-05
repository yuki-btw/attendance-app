<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 4：日時取得機能
 */
class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_current_date_and_time_are_shown_in_ui_format(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 9, 41));

        $response = $this->actingAs(User::factory()->create())->get('/attendance');

        $response->assertOk();
        $response->assertSee('2026年11月5日(木)');
        $response->assertSee('09:41');
    }
}
