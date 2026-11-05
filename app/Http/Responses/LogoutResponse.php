<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * 管理者ログアウトなら管理者ログイン画面へ、それ以外は一般ログイン画面へ移動する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return $request->is('admin/logout')
            ? redirect('/admin/login')
            : redirect('/login');
    }
}
