<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>无权限访问 — {{ config('app.name', 'Membership Pro') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{ --paper:#FFFCF8; --paper2:#F6F1EB; --ink:#141412; --line:#ECE8E0; --accent:#FF3D11; }
body{ background:var(--paper); color:var(--ink); font-family:Inter,system-ui,sans-serif; -webkit-font-smoothing:antialiased; }
.serif{ font-family:"Instrument Serif",Georgia,serif; }
.mono{ font-family:"JetBrains Mono",ui-monospace,monospace; }
.soft{ box-shadow:0 8px 30px rgba(20,20,18,.06), 0 1px 3px rgba(20,20,18,.08); }
</style>
</head>
<body class="min-h-screen flex flex-col">
{{-- top pill --}}
<div class="sticky top-0 z-40 pt-4 px-4 sm:px-6">
  <header class="max-w-[1280px] mx-auto bg-white/90 backdrop-blur-xl border border-[var(--line)] rounded-full h-[56px] flex items-center gap-3 px-3 soft">
    <a href="/" class="flex items-center gap-3 pl-2">
      <span class="w-9 h-9 rounded-full bg-[var(--ink)] text-white grid place-items-center serif text-[18px]">MP</span>
      <span class="hidden sm:block leading-none"><span class="serif text-[17px]">{{ config('app.name', 'Membership Pro') }}</span><span class="mono text-[10px] tracking-[0.16em] opacity-50 block -mt-0.5">MULTI-STORE SAAS</span></span>
    </a>
    <span class="ml-auto mono text-xs opacity-40">403 · 无权限</span>
  </header>
</div>

{{-- backdrop --}}
<div class="flex-1 flex items-center justify-center p-4 sm:p-6">
  {{-- modal card --}}
  <div class="w-full max-w-[520px] bg-white rounded-[28px] border border-[var(--line)] soft overflow-hidden">
    {{-- icon head --}}
    <div class="px-7 sm:px-8 pt-8 pb-6 text-center">
      <div class="w-14 h-14 rounded-full bg-amber-50 border border-amber-200 grid place-items-center mx-auto">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
      </div>
      <h1 class="serif text-[26px] sm:text-[28px] leading-none mt-4">无权限访问</h1>
      <p class="mono text-[11px] tracking-[0.14em] opacity-40 mt-1">PERMISSION DENIED · 403</p>
      <p class="text-sm leading-6 opacity-70 mt-4 max-w-[36ch] mx-auto">
        @if(isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' && $exception->getMessage() !== '')
          {{ $exception->getMessage() }}
        @else
          当前账号<span class="font-semibold opacity-100">为普通会员</span>，仅限商户管理员访问后台。<br>如需管理店铺，请联系商户管理员或申请入驻。
        @endif
      </p>
      @auth
        <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-xs">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          {{ auth()->user()->email }} · {{ auth()->user()->getRoleNames()->first() ?? 'member' }}
        </div>
      @endauth
    </div>

    <div class="px-6 sm:px-8 pb-6">
      <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-4 mono text-xs leading-5">
        <div class="flex items-center gap-2 font-semibold tracking-widest text-[11px] opacity-60 mb-2">
          <span class="w-5 h-5 rounded-full bg-white border border-[var(--line)] grid place-items-center text-[10px]">?</span>
          如何解决
        </div>
        <ul class="space-y-1 opacity-70 list-disc ml-5">
          <li>确认你已使用 <span class="font-mono bg-white px-1 rounded border">tenant@demo.com</span> 等商户管理员账号登录</li>
          <li>普通会员请返回 <a href="/" class="underline text-[var(--accent)]">商城首页</a> 或前往 <a href="/me" class="underline">个人中心</a></li>
          <li>需要开店？<a href="/tenants/register" class="underline text-[var(--accent)]">申请入驻</a> 后即可获得后台权限</li>
        </ul>
      </div>
    </div>

    <div class="px-6 sm:px-8 pb-7 flex flex-col sm:flex-row gap-2.5">
      <a href="/" class="flex-1 inline-flex justify-center items-center gap-2 h-11 rounded-full bg-[var(--ink)] text-white text-sm font-semibold hover:bg-black transition">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.7"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
        返回商城
      </a>
      <a href="/me" class="inline-flex justify-center items-center h-11 px-6 rounded-full border border-[var(--line)] bg-white text-sm font-medium hover:bg-[var(--paper2)] transition">个人中心</a>
      <a href="/tenants/register" class="inline-flex justify-center items-center h-11 px-6 rounded-full border border-[var(--line)] bg-white text-sm font-medium hover:bg-[var(--paper2)] transition">申请入驻</a>
    </div>

    <div class="px-6 sm:px-8 pb-6 mono text-[11px] opacity-40 text-center border-t border-[var(--line)] pt-4">
      若你是商户管理员但仍看到此页，请检查账号角色或联系平台管理员
    </div>
  </div>
</div>

<footer class="py-6 text-center mono text-xs opacity-40">
  {{ config('app.name', 'Membership Pro') }} · shop1.xxx.com 子域隔离 · <a href="/" class="underline">首页</a> · <a href="/api/v1/health" class="underline">API</a>
</footer>
</body>
</html>
