<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    public const STATUS_PENDING = '承認待ち';

    public const STATUS_APPROVED = '承認済み';

    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'approval_status',
        'application_date',
        'new_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
    ];

    protected $casts = [
        'application_date' => 'date',
        'new_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class)->orderBy('break_in');
    }

    /**
     * 申請の出勤時刻を H:i 形式で返す。
     */
    protected function newClockIn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? Carbon::parse($value)->format('H:i') : null,
        );
    }

    /**
     * 申請の退勤時刻を H:i 形式で返す。
     */
    protected function newClockOut(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? Carbon::parse($value)->format('H:i') : null,
        );
    }
}
