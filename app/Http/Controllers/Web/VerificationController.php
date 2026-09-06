<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 邮箱验证：仅当 MEMBERSHIP_EMAIL_VERIFICATION=true 时强制生效（见 EnsureEmailVerifiedIfRequired）。
 * 验证链接为签名 URL（Laravel 标准邮件模板生成），48 小时有效。
 */
class VerificationController extends Controller
{
    // 验证提示页（未验证会员会被重定向到这里）
    public function notice(Request $r)
    {
        return view('auth.verify-email');
    }

    // 邮件里的签名验证链接
    public function verify(Request $r, int $id, string $hash)
    {
        $user = \App\Models\User::findOrFail($id);
        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            return redirect()->route('web.verification.notice')->with('error', '验证链接无效');
        }
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        return redirect('/')->with('success', '邮箱验证成功');
    }

    // 重发验证邮件
    public function resend(Request $r)
    {
        if ($r->user()->hasVerifiedEmail()) {
            return redirect('/')->with('success', '邮箱已验证过');
        }
        $r->user()->sendEmailVerificationNotification();
        return back()->with('status', '验证邮件已重新发送，请查收（含垃圾箱）');
    }
}
