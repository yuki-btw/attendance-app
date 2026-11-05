<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * ダミーデータの乱数を毎回同じにするための固定シード値
     */
    private const RANDOM_SEED = 20261105;

    /**
     * 全ユーザーの勤怠・休憩のダミーデータを作成する。
     */
    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);

        $this->seedReportSampleUser(User::where('email', 'user1@example.com')->firstOrFail());

        User::whereIn('email', ['user2@example.com', 'user3@example.com'])
            ->orderBy('id')
            ->get()
            ->each(fn (User $user) => $this->seedRandomUser($user));
    }

    /**
     * user1：マイ勤怠レポートの期待値どおりになる意図的なデータを作成する。
     * 過去5か月は各月の平日15日を通常勤務、当月は平日17日を通常・残業・遅刻・早退・長時間に振り分ける。
     */
    private function seedReportSampleUser(User $user): void
    {
        collect(range(5, 1))
            ->flatMap(fn (int $monthsAgo): Collection => $this->weekdaysOfMonth(now()->subMonthsNoOverflow($monthsAgo))->take(15))
            ->each(fn (Carbon $date) => $this->createRecord($user, $date, '09:00', '18:00', [['12:00', '13:00']]));

        $patterns = collect()
            ->merge(array_fill(0, 10, ['09:00', '18:00']))
            ->merge(array_fill(0, 3, ['09:00', '20:00']))
            ->merge(array_fill(0, 2, ['09:30', '18:00']))
            ->merge([['09:00', '17:00']])
            ->merge([['08:00', '21:00']]);

        $this->weekdaysOfMonth(now())
            ->reject(fn (Carbon $date): bool => $date->isToday())
            ->take($patterns->count())
            ->values()
            ->each(fn (Carbon $date, int $index) => $this->createRecord(
                $user,
                $date,
                $patterns[$index][0],
                $patterns[$index][1],
                [['12:00', '13:00']],
            ));
    }

    /**
     * user2・user3：直近3か月（今日を除く）の平日にばらつきのある勤怠を作成する。
     */
    private function seedRandomUser(User $user): void
    {
        collect(CarbonPeriod::create(now()->subMonthsNoOverflow(2)->startOfMonth(), today()->subDay()))
            ->filter(fn (Carbon $date): bool => $date->isWeekday())
            ->reject(fn (): bool => mt_rand(1, 10) === 1)
            ->each(function (Carbon $date) use ($user): void {
                $clockIn = Carbon::createFromTime(8, 45)->addMinutes(mt_rand(0, 9) * 5);
                $clockOut = Carbon::createFromTime(17, 30)->addMinutes(mt_rand(0, 30) * 5);
                $breaks = [['12:00', '13:00']];

                if (mt_rand(1, 3) === 1) {
                    $breaks[] = ['15:00', '15:15'];
                }

                $this->createRecord($user, $date, $clockIn->format('H:i'), $clockOut->format('H:i'), $breaks);
            });
    }

    /**
     * 指定した月の平日を日付順に返す。
     *
     * @return Collection<int, Carbon>
     */
    private function weekdaysOfMonth(Carbon $month): Collection
    {
        return collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->filter(fn (Carbon $date): bool => $date->isWeekday())
            ->values();
    }

    /**
     * 勤怠と休憩を作成し、合計時間を計算して保存する。
     *
     * @param  array<int, array{0: string, 1: string}>  $breaks
     */
    private function createRecord(User $user, Carbon $date, string $clockIn, string $clockOut, array $breaks): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        $record->breaks()->createMany(
            collect($breaks)->map(fn (array $break): array => ['break_in' => $break[0], 'break_out' => $break[1]])->all()
        );

        $record->recalculateTotals();
    }
}
