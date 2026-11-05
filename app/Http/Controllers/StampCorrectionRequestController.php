<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 申請一覧を表示する。管理者は全一般ユーザーの申請、一般ユーザーは自分の申請を表示する。
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('admin.admin-application-list', [
                'applications' => Application::with(['user', 'AttendanceRecord'])->latest()->get(),
            ]);
        }

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $user->applications()
                ->latest()
                ->get()
                ->map(fn (Application $application): array => [
                    'id' => $application->id,
                    'approval_status' => $application->approval_status,
                    'date' => $application->new_date->format('Y/m/d'),
                    'comment' => $application->comment,
                    'application_date' => $application->application_date->format('Y/m/d'),
                ])
                ->all(),
        ]);
    }

    /**
     * 勤怠詳細の「修正」ボタンの処理。
     * 一般ユーザーは修正申請を「承認待ち」で登録し、管理者は勤怠を直接修正する。
     */
    public function store(AttendanceCorrectionRequest $request, int $id, AttendanceCorrectionService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
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

        $record = $user->attendanceRecords()->findOrFail($id);

        if ($record->pendingApplication()) {
            return redirect('/attendance/'.$record->id);
        }

        DB::transaction(function () use ($request, $user, $record): void {
            $application = $user->applications()->create([
                'attendance_record_id' => $record->id,
                'approval_status' => Application::STATUS_PENDING,
                'application_date' => today()->toDateString(),
                'new_date' => $record->date->toDateString(),
                'new_clock_in' => $request->input('new_clock_in'),
                'new_clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $application->proposalBreaks()->createMany($request->breaks());
        });

        return redirect('/attendance/'.$record->id);
    }

    /**
     * 申請した日の勤怠詳細画面を表示する。
     */
    public function show(Request $request, int $id, AttendanceDetailService $service): View
    {
        /** @var User $user */
        $user = $request->user();

        $application = $user->applications()->with('attendanceRecord.breaks')->findOrFail($id);
        $record = $application->attendanceRecord;
        $pendingApplication = $record->pendingApplication();

        return view('user.user-detail', [
            'user' => $user,
            'data' => $service->format($record, $pendingApplication) + ['application' => $pendingApplication],
        ]);
    }
}
