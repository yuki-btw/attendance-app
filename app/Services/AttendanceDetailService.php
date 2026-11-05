<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;

class AttendanceDetailService
{
    /**
     * 勤怠詳細画面に渡す配列を作る。
     * 承認待ちの申請があれば申請内容を、バリデーションエラーで戻ってきた場合は入力内容を表示する。
     *
     * @return array{id: int, year: string, date: string, clock_in: string, clock_out: string, breaks: array<int, array{break_in: string, break_out: string}>, comment: ?string}
     */
    public function format(AttendanceRecord $record, ?Application $pendingApplication = null): array
    {
        $data = [
            'id' => $record->id,
            'year' => $record->date->format('Y年'),
            'date' => $record->date->format('n月j日'),
        ];

        if ($pendingApplication) {
            return $data + [
                'clock_in' => $pendingApplication->new_clock_in,
                'clock_out' => $pendingApplication->new_clock_out,
                'breaks' => $pendingApplication->proposalBreaks
                    ->map(fn (ProposalBreak $break): array => [
                        'break_in' => MonthlyAttendanceService::toHourMinute($break->break_in),
                        'break_out' => MonthlyAttendanceService::toHourMinute($break->break_out),
                    ])
                    ->values()
                    ->all(),
                'comment' => $pendingApplication->comment,
            ];
        }

        return $data + [
            'clock_in' => old('new_clock_in', MonthlyAttendanceService::toHourMinute($record->clock_in)),
            'clock_out' => old('new_clock_out', MonthlyAttendanceService::toHourMinute($record->clock_out)),
            'breaks' => $this->oldBreaks() ?? $record->breaks
                ->map(fn (AttendanceBreak $break): array => [
                    'break_in' => MonthlyAttendanceService::toHourMinute($break->break_in),
                    'break_out' => MonthlyAttendanceService::toHourMinute($break->break_out),
                ])
                ->values()
                ->all(),
            'comment' => old('comment', $record->comment),
        ];
    }

    /**
     * 直前に送信された休憩の入力内容を返す。末尾の空欄行は除く（画面側で空欄を1行追加するため）。
     * 送信内容がなければ null を返す。
     *
     * @return array<int, array{break_in: string, break_out: string}>|null
     */
    private function oldBreaks(): ?array
    {
        $breakIns = old('new_break_in');
        $breakOuts = old('new_break_out');

        if (! is_array($breakIns) && ! is_array($breakOuts)) {
            return null;
        }

        $rows = collect(array_keys(($breakIns ?? []) + ($breakOuts ?? [])))
            ->sort()
            ->values()
            ->map(fn (int $index): array => [
                'break_in' => (string) ($breakIns[$index] ?? ''),
                'break_out' => (string) ($breakOuts[$index] ?? ''),
            ]);

        while ($rows->isNotEmpty() && $rows->last()['break_in'] === '' && $rows->last()['break_out'] === '') {
            $rows->pop();
        }

        return $rows->all();
    }
}
