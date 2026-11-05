<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MonthlyAttendanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffAttendanceController extends Controller
{
    /**
     * 選んだスタッフの勤怠を1か月分表示する。
     */
    public function index(Request $request, int $id, MonthlyAttendanceService $service): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);
        $date = $service->resolveMonth($request->query('date'));

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $service->formatMonth($user, $date),
        ]);
    }
}
