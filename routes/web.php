<?php

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
