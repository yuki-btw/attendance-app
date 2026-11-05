<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionService
{
    /**
     * 勤怠の出勤・退勤・備考と休憩を指定内容で更新し、合計時間を再計算する。
     * 休憩はいったん削除して作り直す。
     *
     * @param  array<int, array{break_in: string, break_out: string}>  $breaks
     */
    public function correct(AttendanceRecord $record, string $clockIn, string $clockOut, ?string $comment, array $breaks): void
    {
        DB::transaction(function () use ($record, $clockIn, $clockOut, $comment, $breaks): void {
            $record->update([
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'comment' => $comment,
            ]);

            $record->breaks()->delete();
            $record->breaks()->createMany($breaks);

            $record->recalculateTotals();
        });
    }
}
