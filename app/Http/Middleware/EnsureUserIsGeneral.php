<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsGeneral
{
    /**
     * 一般ユーザー専用の画面に管理者がアクセスした場合は、管理者の勤怠一覧へ移動する。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return redirect('/admin/attendance/list');
        }

        return $next($request);
    }
}
