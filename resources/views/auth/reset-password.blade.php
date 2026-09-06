@extends('layouts.shop')
@section('title','重置密码')
@section('content')
<div class="max-w-[440px] mx-auto">
    <div class="relative rounded-[28px] bg-white border border-[var(--line)] soft overflow-hidden">
        <div class="relative px-7 sm:px-8 pt-8 pb-6">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-[0.14em] opacity-70">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> AUTH — NEW PASSWORD
            </div>
            <h1 class="serif text-[30px] leading-none tracking-[-0.03em] mt-4">设置新密码</h1>
            <p class="mono text-xs leading-5 opacity-50 mt-2">至少 8 位，含大小写字母与数字</p>
        </div>

        <form method="POST" action="{{ route('web.password.update') }}" class="relative px-7 sm:px-8 pb-7 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">邮箱</span>
                <input name="email" type="email" value="{{ old('email', $email) }}" required
                       class="w-full h-11 px-4 mt-1.5 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm transition">
                @error('email')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">新密码</span>
                <input name="password" type="password" required autocomplete="new-password"
                       class="w-full h-11 px-4 mt-1.5 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm transition">
                @error('password')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">确认新密码</span>
                <input name="password_confirmation" type="password" required autocomplete="new-password"
                       class="w-full h-11 px-4 mt-1.5 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm transition">
            </label>
            <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white mono text-sm tracking-wider hover:opacity-90 transition">重置密码</button>
        </form>
    </div>
</div>
@endsection
