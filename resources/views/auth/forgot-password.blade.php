@extends('layouts.shop')
@section('title','找回密码')
@section('content')
<div class="max-w-[440px] mx-auto">
    <div class="relative rounded-[28px] bg-white border border-[var(--line)] soft overflow-hidden">
        <div class="relative px-7 sm:px-8 pt-8 pb-6">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-[0.14em] opacity-70">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> AUTH — RESET
            </div>
            <h1 class="serif text-[30px] leading-none tracking-[-0.03em] mt-4">找回密码</h1>
            <p class="mono text-xs leading-5 opacity-50 mt-2">输入注册邮箱，我们会发送重置链接</p>
        </div>

        <form method="POST" action="{{ route('web.password.email') }}" class="relative px-7 sm:px-8 pb-7 space-y-4">
            @csrf
            @if (session('status'))
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 mono text-xs text-emerald-700">{{ session('status') }}</div>
            @endif
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">邮箱</span>
                <input name="email" type="email" value="{{ old('email') }}" required
                       class="w-full h-11 px-4 mt-1.5 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm transition">
                @error('email')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>
            <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white mono text-sm tracking-wider hover:opacity-90 transition">发送重置链接</button>
            <div class="text-center mono text-xs opacity-50">
                <a href="{{ route('web.login') }}" class="hover:opacity-100">返回登录</a>
            </div>
        </form>
    </div>
</div>
@endsection
