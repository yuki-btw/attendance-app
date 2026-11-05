<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class MonthlyAttendanceService
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * クエリ文字列の年月（Y-m）を解釈し、不正・未指定なら当月の1日を返す。
     */
    public function resolveMonth(?string $yearMonth): Carbon
    {
        if ($yearMonth && preg_match('/^\d{4}-\d{2}$/', $yearMonth) && Carbon::canBeCreatedFromFormat($yearMonth, 'Y-m')) {
            return Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        }

        return today()->startOfMonth();
    }

    /**
     * 指定ユーザーの1か月分の勤怠を、月の1日〜末日の全日分の配列にして返す。
     * 勤怠のない日は各項目を空文字にする。
     *
     * @return array<int, array{id: int|string, date: string, clock_in: string, clock_out: string, total_break_time: string, total_time: string}>
     */
    public function formatMonth(User $user, Carbon $month): array
    {
        $records = $user->attendanceRecords()
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecord $record): string => $record->date->toDateString());

        return collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->map(function (Carbon $day) use ($records): array {
                $record = $records->get($day->toDateString());

                return [
                    'id' => $record?->id ?? '',
                    'date' => $day->format('m/d').'('.self::WEEKDAYS[$day->dayOfWeek].')',
                    'clock_in' => $record ? self::toHourMinute($record->clock_in) : '',
                    'clock_out' => $record?->clock_out ? self::toHourMinute($record->clock_out) : '',
                    'total_break_time' => $record?->total_break_time ?? '',
                    'total_time' => $record?->total_time ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 日付を「2026年11月5日(木)」の形式にする。
     */
    public static function formatJapaneseDate(Carbon $date): string
    {
        return $date->format('Y年n月j日').'('.self::WEEKDAYS[$date->dayOfWeek].')';
    }

    /**
     * 時刻（H:i:s）を H:i にする。空なら空文字を返す。
     */
    public static function toHourMinute(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
