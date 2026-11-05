<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'user_id' => fn (array $attributes) => AttendanceRecord::find($attributes['attendance_record_id'])->user_id,
            'approval_status' => Application::STATUS_PENDING,
            'application_date' => today()->toDateString(),
            'new_date' => fn (array $attributes) => AttendanceRecord::find($attributes['attendance_record_id'])->date->toDateString(),
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '電車遅延のため',
        ];
    }

    /**
     * 承認済みの状態にする。
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Application::STATUS_APPROVED,
        ]);
    }
}
