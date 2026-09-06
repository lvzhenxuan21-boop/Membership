<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * 密码找回：Web 表单 + API 共用 Password broker。
 * 防枚举：发送重置链接时无论邮箱是否存在都返回同一话术。
 * 重置链接指向 Web 页面（password.reset 命名路由），API 用户同样通过邮件链接完成。
 */
class PasswordResetController extends Controller
{
    // ---------- Web ----------

    public function requestForm(Request $r)
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));
        return back()->with('status', '重置链接已发送至你的邮箱（若该邮箱已注册），请在 60 分钟内完成重置');
    }

    public function resetForm(Request $r, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $r->query('email', '')]);
    }

    public function reset(Request $r)
    {
        $data = $this->validateReset($r);
        $status = $this->brokerReset($data);
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('web.login')->with('success', '密码已重置，请用新密码登录')
            : back()->withErrors(['email' => __($status)])->withInput();
    }

    // ---------- API ----------

    public function apiSendResetLink(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));
        return response()->json(['message' => '若该邮箱已注册，重置链接已发送（60 分钟内有效）']);
    }

    public function apiReset(Request $r)
    {
        $data = $this->validateReset($r);
        $status = $this->brokerReset($data);
        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => '密码已重置，请使用新密码登录'])
            : response()->json(['message' => __($status)], 422);
    }

    private function validateReset(Request $r): array
    {
        return $r->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ]);
    }

    private function brokerReset(array $data): string
    {
        return Password::reset(
            collect($data)->only('email', 'password', 'password_confirmation', 'token')->all(),
            function ($user, $password) {
                // hashed cast 自动哈希；换密码后踢掉其他会话语义由 remember_token 轮换承担
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            },
        );
    }
}
