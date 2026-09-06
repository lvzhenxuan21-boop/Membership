<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 可选邮箱验证门禁：仅当 MEMBERSHIP_EMAIL_VERIFICATION=true 时拦截未验证会员的
 * 消费动作（下单/订阅/签到）；默认关闭，系统行为与 1.0 一致。运营角色不受限。
 */
class EnsureEmailVerifiedIfRequired
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('membership.email_verification')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || $user->hasVerifiedEmail() || $user->hasAnyRole(['super_admin', 'admin', 'tenant_admin', 'staff'])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => '请先完成邮箱验证后再进行该操作'], 403);
        }
        return redirect()->route('web.verification.notice')->with('error', '请先完成邮箱验证');
    }
}
