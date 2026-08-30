@extends('layouts.shop')
@section('title','登录')
@section('content')
<div class="max-w-[440px] mx-auto">
    {{-- card --}}
    <div class="relative rounded-[28px] bg-white border border-[var(--line)] soft overflow-hidden">
        {{-- subtle blobs --}}
        <div class="pointer-events-none absolute -top-16 -right-16 w-64 h-64 rounded-full opacity-[0.07]" style="background:radial-gradient(circle at 30% 30%, #FF6B35 0%, #FFD6A8 55%, transparent 75%)"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-16 w-72 h-72 rounded-full opacity-[0.06]" style="background:radial-gradient(circle at 50% 50%, #6B7F72 0%, #C8D6C2 50%, transparent 75%)"></div>

        <div class="relative px-7 sm:px-8 pt-8 pb-6">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-[0.14em] opacity-70">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> AUTH — SIGN IN
            </div>
            <h1 class="serif text-[30px] leading-none tracking-[-0.03em] mt-4">登录</h1>
            <p class="mono text-xs leading-5 opacity-50 mt-2">欢迎回来 · 商城、订单、积分与权益在此聚合</p>
        </div>

        <form method="POST" action="{{ route('web.login.post') }}" class="relative px-7 sm:px-8 pb-7 space-y-4">
            @csrf
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">邮箱</span>
                <div class="relative mt-1.5">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                    </span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required
                           class="w-full h-11 pl-12 pr-4 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm placeholder:text-zinc-400 transition">
                </div>
                @error('email')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">密码</span>
                <div class="relative mt-1.5" x-data="{show:false}">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="16" r="1.6"/></svg>
                    </span>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" placeholder="••••••••" autocomplete="current-password" required
                           class="w-full h-11 pl-12 pr-11 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm transition">
                    <button type="button" @click="show=!show" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-500 hover:bg-[var(--paper2)] transition" :title="show ? '隐藏' : '显示'">
                        <svg x-show="!show" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.2"/></svg>
                        <svg x-show="show" x-cloak width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3l18 18"/><path d="M10.6 10.6A2 2 0 0 0 13.4 13.4"/><path d="M9.7 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a15.5 15.5 0 0 1-3 4"/><path d="M14.8 14.8A10 10 0 0 1 12 17c-7 0-10-7-10-7a15.5 15.5 0 0 1 5.1-4.9"/></svg>
                    </button>
                </div>
                @error('password')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>

            <div class="flex items-center justify-between mono text-xs pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer opacity-70 hover:opacity-100">
                    <input type="checkbox" name="remember" value="1" checked class="w-3.5 h-3.5 rounded border-[var(--line)] accent-[var(--ink)]">
                    记住我
                </label>
                <a href="/tenants/register" class="opacity-40 hover:opacity-100 hover:underline">想开店？去入驻 →</a>
            </div>

            <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white mono text-sm font-semibold tracking-wide hover:bg-black transition flex items-center justify-center gap-2 soft">
                登录 — SIGN IN <span class="opacity-60">→</span>
            </button>

            <div class="flex items-center gap-3 py-1">
                <span class="h-px flex-1 bg-[var(--line)]"></span>
                <span class="mono text-[11px] tracking-widest opacity-30">OR</span>
                <span class="h-px flex-1 bg-[var(--line)]"></span>
            </div>

            <div class="grid grid-cols-3 gap-2 mono text-[11px]">
                <span class="inline-flex items-center justify-center gap-1.5 h-8 rounded-full bg-[var(--paper2)] border border-[var(--line)]">✓ 钱包</span>
                <span class="inline-flex items-center justify-center gap-1.5 h-8 rounded-full bg-[var(--paper2)] border border-[var(--line)]">✓ 积分</span>
                <span class="inline-flex items-center justify-center gap-1.5 h-8 rounded-full bg-[var(--paper2)] border border-[var(--line)]">✓ 订单</span>
            </div>
        </form>

        <div class="px-7 sm:px-8 pb-7">
            <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] px-4 py-3 flex items-center justify-between gap-3">
                <span class="mono text-xs opacity-60">没有账号？</span>
                <a href="{{ route('web.register') }}" class="inline-flex items-center gap-1.5 h-8 px-4 rounded-full bg-white border border-[var(--line)] mono text-xs font-semibold hover:bg-[var(--ink)] hover:text-white hover:border-[var(--ink)] transition">去注册 — CREATE <span>↗</span></a>
            </div>
            <div class="mono text-[11px] leading-relaxed opacity-30 text-center mt-3">
                商家用户请使用入驻时的管理员邮箱登录，商户后台入口在页面底部
            </div>
        </div>
    </div>

    <div class="mono text-[11px] opacity-30 text-center mt-4">
        登录即代表同意 商家入驻协议 与 隐私政策
    </div>
</div>
@endsection
