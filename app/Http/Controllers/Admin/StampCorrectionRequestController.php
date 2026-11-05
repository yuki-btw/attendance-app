<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ProposalBreak;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 修正申請の内容を表示する。
     */
    public function show(int $attendance_correct_request_id): View
    {
        $application = Application::with(['user', 'proposalBreaks'])->findOrFail($attendance_correct_request_id);

        return view('admin.admin-application-detail', [
            'user' => $application->user,
            'application' => $application,
        ]);
    }

    /**
     * 修正申請を承認し、一般ユーザーの勤怠を申請内容で更新する。
     */
    public function approve(int $attendance_correct_request_id, AttendanceCorrectionService $service): RedirectResponse
    {
        $application = Application::with(['attendanceRecord', 'proposalBreaks'])->findOrFail($attendance_correct_request_id);

        if ($application->approval_status === Application::STATUS_PENDING) {
            DB::transaction(function () use ($application, $service): void {
                $application->update(['approval_status' => Application::STATUS_APPROVED]);

                $service->correct(
                    $application->attendanceRecord,
                    $application->new_clock_in,
                    $application->new_clock_out,
                    $application->comment,
                    $application->proposalBreaks
                        ->map(fn (ProposalBreak $break): array => [
                            'break_in' => $break->break_in,
                            'break_out' => $break->break_out,
                        ])
                        ->all(),
                );
            });
        }

        return redirect('/stamp_correction_request/approve/'.$application->id);
    }
}
