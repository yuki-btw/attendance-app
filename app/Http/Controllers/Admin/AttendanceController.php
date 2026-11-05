<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceDetailService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * その日の全一般ユーザーの勤怠を一覧表示する。
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));
        $users = User::where('admin_status', false)->orderBy('id')->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->format('Y-m-d'),
            'nextDay' => $date->copy()->addDay()->format('Y-m-d'),
            'users' => $users,
            'attendanceRecords' => AttendanceRecord::whereDate('date', $date)
                ->whereIn('user_id', $users->pluck('id'))
                ->get(),
        ]);
    }

    /**
     * 勤怠の詳細を表示する。
     */
    public function show(int $id, AttendanceDetailService $service): View
    {
        $record = AttendanceRecord::with(['user', 'breaks'])->findOrFail($id);

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => $service->format($record),
        ]);
    }

    /**
     * 勤怠を直接修正する（承認は不要）。
     */
    public function update(AttendanceCorrectionRequest $request, int $id, AttendanceCorrectionService $service): RedirectResponse
    {
        $record = AttendanceRecord::findOrFail($id);

        $service->correct(
            $record,
            $request->input('new_clock_in'),
            $request->input('new_clock_out'),
            $request->input('comment'),
            $request->breaks(),
        );

        return redirect('/admin/attendance/'.$record->id);
    }

    /**
     * クエリ文字列の日付（Y-m-d）を解釈し、不正・未指定なら今日を返す。
     */
    private function resolveDate(?string $date): Carbon
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        }

        return today();
    }
}
