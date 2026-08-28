@extends('layouts.shop')
@section('title','注册')
@section('content')
<div class="max-w-[480px] mx-auto">
    <div class="relative rounded-[28px] bg-white border border-[var(--line)] soft overflow-hidden">
        <div class="pointer-events-none absolute -top-16 -right-16 w-64 h-64 rounded-full opacity-[0.07]" style="background:radial-gradient(circle at 30% 30%, #FF6B35 0%, #FFD6A8 55%, transparent 75%)"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-20 w-72 h-72 rounded-full opacity-[0.06]" style="background:radial-gradient(circle at 50% 50%, #6B7F72 0%, #C8D6C2 50%, transparent 75%)"></div>

        <div class="relative px-7 sm:px-8 pt-8 pb-6">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-[0.14em] opacity-70">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--accent)]"></span> AUTH — CREATE ACCOUNT
            </div>
            <h1 class="serif text-[30px] leading-none tracking-[-0.03em] mt-4">注册</h1>
            <p class="mono text-xs leading-5 opacity-50 mt-2">30 秒成为普通会员 · 自动创建 档案 + 钱包 · 零门槛逛店</p>
            <div class="mt-3 inline-flex flex-wrap gap-1.5 mono text-[11px]">
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700">✓ 普通会员 Lv1</span>
                <span class="px-2.5 py-1 rounded-full bg-white border border-[var(--line)] opacity-60">钱包 0</span>
                <span class="px-2.5 py-1 rounded-full bg-white border border-[var(--line)] opacity-60">积分 0</span>
            </div>
        </div>

        <form method="POST" action="{{ route('web.register.post') }}" class="relative px-7 sm:px-8 pb-7 space-y-3.5">
            @csrf
            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">昵称</span>
                <div class="relative mt-1.5">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.5"/><path d="M5 19a7 7 0 0 1 14 0"/></svg>
                    </span>
                    <input name="name" value="{{ old('name') }}" placeholder="怎么称呼你？" autocomplete="nickname" required
                           class="w-full h-11 pl-12 pr-4 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm placeholder:text-zinc-400 transition">
                </div>
                @error('name')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">邮箱</span>
                <div class="relative mt-1.5">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                    </span>
                    <input name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required
                           class="w-full h-11 pl-12 pr-4 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm placeholder:text-zinc-400 transition">
                </div>
                @error('email')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <label class="block">
                    <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">密码</span>
                    <div class="relative mt-1.5">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        </span>
                        <input name="password" type="password" placeholder="≥8位" autocomplete="new-password" required
                               class="w-full h-11 pl-12 pr-4 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm placeholder:text-zinc-400 transition">
                    </div>
                    @error('password')<span class="mono text-xs text-red-600 ml-1 mt-1 block">{{ $message }}</span>@enderror
                </label>
                <label class="block">
                    <span class="mono text-[11px] tracking-[0.16em] opacity-50 ml-1">确认密码</span>
                    <div class="relative mt-1.5">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center text-zinc-400">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 12l3 3 9-9"/><circle cx="12" cy="12" r="9"/></svg>
                        </span>
                        <input name="password_confirmation" type="password" placeholder="再输一次" autocomplete="new-password" required
                               class="w-full h-11 pl-12 pr-4 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] focus:outline-none mono text-sm placeholder:text-zinc-400 transition">
                    </div>
                </label>
            </div>

            <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] px-4 py-3 mono text-xs leading-5 opacity-70">
                注册即同意 <span class="font-semibold opacity-100">会员自动建档</span>：分配 普通会员 Lv1、总店 Branch、钱包余额 0，登录后可签到、购券、订阅。
            </div>

            <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white mono text-sm font-semibold tracking-wide hover:bg-black transition flex items-center justify-center gap-2 soft">
                注册并登录 — CREATE <span class="opacity-60">↗</span>
            </button>

            <div class="flex items-center gap-3 py-1">
                <span class="h-px flex-1 bg-[var(--line)]"></span>
                <span class="mono text-[11px] tracking-widest opacity-30">ALREADY MEMBER?</span>
                <span class="h-px flex-1 bg-[var(--line)]"></span>
            </div>
        </form>

        <div class="px-7 sm:px-8 pb-7">
            <div class="rounded-2xl bg-[var(--ink)] text-white px-4 py-3 flex items-center justify-between gap-3">
                <span class="mono text-xs opacity-80">已有账号？直接登录</span>
                <a href="{{ route('web.login') }}" class="inline-flex items-center gap-1.5 h-8 px-4 rounded-full bg-white text-[var(--ink)] mono text-xs font-semibold hover:bg-[var(--paper2)] transition">去登录 — SIGN IN <span>→</span></a>
            </div>
            <div class="mono text-[11px] leading-relaxed opacity-30 text-center mt-3">
                商户入驻请走 <a href="/tenants/register" class="underline">创建店铺</a> · 将生成 <span class="font-medium opacity-60">slug.xxx.com</span>
            </div>
        </div>
    </div>
</div>
@endsection
