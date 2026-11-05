<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * 管理者は勤怠一覧（管理者）へ、一般ユーザーは打刻画面へ移動する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return $request->user()->isAdmin()
            ? redirect('/admin/attendance/list')
            : redirect('/attendance');
    }
}
