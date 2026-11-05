<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * 会員登録後は打刻画面へ移動する。
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect('/attendance');
    }
}
