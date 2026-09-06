@extends('layouts.shop')
@section('title','邮箱验证')
@section('content')
<div class="max-w-[440px] mx-auto">
    <div class="relative rounded-[28px] bg-white border border-[var(--line)] soft overflow-hidden">
        <div class="relative px-7 sm:px-8 pt-8 pb-7">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-[0.14em] opacity-70">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> VERIFY EMAIL
            </div>
            <h1 class="serif text-[30px] leading-none tracking-[-0.03em] mt-4">验证你的邮箱</h1>
            <p class="mono text-xs leading-5 opacity-50 mt-2">验证邮件已发送至 <span class="opacity-100">{{ auth()->user()->email }}</span>，点击邮件里的链接即可完成验证。</p>
            @if (session('status'))
                <div class="mt-4 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 mono text-xs text-emerald-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mt-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 mono text-xs text-red-700">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('web.verification.send') }}" class="mt-6 space-y-3">
                @csrf
                <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white mono text-sm tracking-wider hover:opacity-90 transition">重新发送验证邮件</button>
            </form>
            <div class="text-center mono text-xs opacity-50 mt-4">
                <form method="POST" action="{{ route('web.logout') }}" class="inline">@csrf
                    <button type="submit" class="hover:opacity-100 underline">换一个账号</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
