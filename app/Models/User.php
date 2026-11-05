<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const STATUS_OFF_DUTY = '勤務外';

    public const STATUS_WORKING = '出勤中';

    public const STATUS_ON_BREAK = '休憩中';

    public const STATUS_FINISHED = '退勤済';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function isAdmin(): bool
    {
        return $this->admin_status === true;
    }

    /**
     * 今日の勤怠から勤務状態（勤務外／出勤中／休憩中／退勤済）を返す。
     */
    protected function attendanceStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $todayRecord = $this->todayAttendanceRecord();

                if (! $todayRecord) {
                    return self::STATUS_OFF_DUTY;
                }

                if ($todayRecord->clock_out !== null) {
                    return self::STATUS_FINISHED;
                }

                if ($todayRecord->breaks->contains(fn (AttendanceBreak $break): bool => $break->break_out === null)) {
                    return self::STATUS_ON_BREAK;
                }

                return self::STATUS_WORKING;
            },
        );
    }

    public function todayAttendanceRecord(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->with('breaks')
            ->whereDate('date', today())
            ->first();
    }
}
