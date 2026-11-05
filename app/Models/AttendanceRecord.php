<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'total_break_time',
        'total_time',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class)->orderBy('break_in');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 休憩の合計と勤務の合計（退勤 − 出勤 − 休憩）を計算して保存する。
     * 退勤していない場合は合計を空にする。
     */
    public function recalculateTotals(): void
    {
        $this->load('breaks');

        $breakMinutes = $this->breaks
            ->filter(fn (AttendanceBreak $break): bool => $break->break_out !== null)
            ->sum(fn (AttendanceBreak $break): int => self::minutesBetween($break->break_in, $break->break_out));

        if ($this->clock_out === null) {
            $this->total_break_time = null;
            $this->total_time = null;
        } else {
            $workMinutes = max(self::minutesBetween($this->clock_in, $this->clock_out) - $breakMinutes, 0);
            $this->total_break_time = self::minutesToTime($breakMinutes);
            $this->total_time = self::minutesToTime($workMinutes);
        }

        $this->save();
    }

    public static function minutesBetween(string $from, string $to): int
    {
        return (int) Carbon::createFromTimeString($from)->diffInMinutes(Carbon::createFromTimeString($to), false);
    }

    public static function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
