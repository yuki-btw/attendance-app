<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceDetailService;
use App\Services\MonthlyAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 打刻画面を表示する。
     */
    public function index(Request $request): View
    {
        $now = now();

        return view('user.attendance-register', [
            'user' => $request->user(),
            'formattedDate' => MonthlyAttendanceService::formatJapaneseDate($now),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 押したボタン（出勤／休憩入／休憩戻／退勤）に応じて時刻を記録する。
     * 今の勤務状態で押せないボタンが送られた場合は何もしない。
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['clock_in', 'clock_out', 'break_in', 'break_out'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $status = $user->attendance_status;
        $action = $request->input('action');
        $time = now()->format('H:i');

        if ($action === 'clock_in' && $status === User::STATUS_OFF_DUTY) {
            $user->attendanceRecords()->create([
                'date' => today()->toDateString(),
                'clock_in' => $time,
            ]);
        } elseif ($action === 'break_in' && $status === User::STATUS_WORKING) {
            $user->todayAttendanceRecord()->breaks()->create(['break_in' => $time]);
        } elseif ($action === 'break_out' && $status === User::STATUS_ON_BREAK) {
            $record = $user->todayAttendanceRecord();
            $record->breaks()->whereNull('break_out')->update(['break_out' => $time]);
            $record->recalculateTotals();
        } elseif ($action === 'clock_out' && $status === User::STATUS_WORKING) {
            $record = $user->todayAttendanceRecord();
            $record->clock_out = $time;
            $record->recalculateTotals();
        }

        return redirect('/attendance');
    }

    /**
     * 自分の勤怠を1か月分表示する。
     */
    public function list(Request $request, MonthlyAttendanceService $service): View
    {
        $date = $service->resolveMonth($request->query('date'));

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $service->formatMonth($request->user(), $date),
        ]);
    }

    /**
     * 自分の勤怠の詳細を表示する。管理者は管理者用の詳細画面へ移動する。
     */
    public function show(Request $request, int $id, AttendanceDetailService $service): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect('/admin/attendance/'.$id);
        }

        $record = $user->attendanceRecords()->with('breaks')->findOrFail($id);

        $pendingApplication = $record->pendingApplication();

        return view('user.user-detail', [
            'user' => $user,
            'data' => $service->format($record, $pendingApplication) + ['application' => $pendingApplication],
        ]);
    }
}
