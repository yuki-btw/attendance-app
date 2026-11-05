<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 会員登録・一般ログイン（/register, /login, /logout）は Fortify が登録する。
|
*/

Route::get('/', function () {
    return auth()->user()?->isAdmin()
        ? redirect('/admin/attendance/list')
        : redirect('/attendance');
});

Route::prefix('admin')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('guest');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware(['guest', 'throttle:login']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth');
});

Route::middleware('auth')->group(function () {
    Route::middleware('general')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::get('/attendance/list', [AttendanceController::class, 'list']);
        Route::get('/application/{id}', [StampCorrectionRequestController::class, 'show'])->whereNumber('id');
    });

    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/detail/{id}', [StampCorrectionRequestController::class, 'store'])->whereNumber('id');
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [StampCorrectionRequestController::class, 'store'])->whereNumber('id');
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index']);
});

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index']);
    Route::get('/attendance/staff/{id}', [StaffAttendanceController::class, 'index'])->whereNumber('id');
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [AdminAttendanceController::class, 'update'])->whereNumber('id');
    Route::get('/staff/list', [StaffController::class, 'index']);
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminStampCorrectionRequestController::class, 'show'])
        ->whereNumber('attendance_correct_request_id');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminStampCorrectionRequestController::class, 'approve'])
        ->whereNumber('attendance_correct_request_id');
});
