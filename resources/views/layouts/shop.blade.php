<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $tenant = isset($tenant) ? $tenant : (app()->bound('currentTenant') ? app('currentTenant') : null); @endphp
    <title>@yield('title', (($tenant?->name ?? 'Membership Pro').' — 多商户电商 SaaS'))</title>
    <meta name="description" content="@yield('metaDescription', (($tenant?->name ?? 'Membership Pro').' — 独立域名的品牌网店，会员营销与全渠道收款一站配齐'))">
    <meta property="og:title" content="@yield('title', (($tenant?->name ?? 'Membership Pro').' — 多商户电商 SaaS'))">
    <meta property="og:description" content="@yield('metaDescription', 'Membership Pro — 30 秒免费开通属于你的品牌网店')">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Newsreader:opsz,wght@6..72,300;6..72,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root{ --paper:#FFFCF8; --paper2:#F6F1EB; --ink:#141412; --line:#ECE8E0; --accent:#FF3D11; --sage:#6B7F72; --sand:#EADFCB; }
        html{ scroll-behavior:smooth; }
        body{ background:var(--paper); color:var(--ink); font-family:Inter,system-ui,-apple-system,sans-serif; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility; }
        .serif{ font-family:"Instrument Serif",Georgia,serif; }
        .news{ font-family:"Newsreader",Georgia,serif; }
        .mono{ font-family:"JetBrains Mono",ui-monospace,monospace; }
        ::selection{ background:#FFE4D6; }
        :focus-visible{ outline:2px solid var(--ink); outline-offset:2px; border-radius:4px; }
        [x-cloak]{ display:none !important; }
        /* soft shadow */
        .soft{ box-shadow:0 8px 30px rgba(20,20,18,.06), 0 1px 3px rgba(20,20,18,.08); }
        .soft-hover:hover{ box-shadow:0 16px 40px rgba(20,20,18,.10), 0 2px 8px rgba(20,20,18,.08); }
        /* hide scrollbar */
        .no-scrollbar::-webkit-scrollbar{ display:none; }
        .no-scrollbar{ -ms-overflow-style:none; scrollbar-width:none; }
        @media (prefers-reduced-motion:no-preference){
            .lift{ transition: transform .5s cubic-bezier(.16,1,.3,1), box-shadow .5s; }
            .lift:hover{ transform: translateY(-4px); }
        }
    </style>
    @stack('head')
</head>
<body x-data="cartStore()" x-init="init()" class="min-h-screen flex flex-col">
@php
$tenant = isset($tenant) ? $tenant : (app()->bound('currentTenant') ? app('currentTenant') : null);
$__canAccessAdmin = auth()->check() ? auth()->user()->hasAnyRole(['super_admin','admin','tenant_admin','staff']) : false;
@endphp

{{-- header: floating pill --}}
<div class="sticky top-0 z-40 pt-3 sm:pt-4 px-3 sm:px-6">
    <header class="max-w-[1280px] mx-auto bg-white/90 backdrop-blur-xl border border-[var(--line)] rounded-full h-[56px] flex items-center gap-3 px-2 sm:px-3 soft">
        <a href="{{ url('/') }}" class="flex items-center gap-3 pl-2 pr-3 shrink-0">
            <span class="w-9 h-9 rounded-full bg-[var(--ink)] text-white grid place-items-center serif text-[18px] leading-none">MP</span>
            <span class="hidden sm:block leading-none">
                <span class="serif text-[17px] tracking-[-0.02em]">Membership Pro</span>
                <span class="mono text-[10px] tracking-[0.16em] opacity-50 block -mt-0.5">MULTI-STORE SAAS</span>
            </span>
        </a>

        @if($tenant)
            <span class="hidden lg:inline-flex items-center gap-2 h-7 px-3 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ $tenant->slug }}.xxx.com
            </span>
        @endif

        <form action="{{ url('/') }}" method="GET" class="hidden md:flex flex-1 max-w-[420px] mx-2 items-center gap-2">
            <div class="flex-1 relative">
                <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="搜索商品、品牌…" class="w-full h-9 pl-9 pr-3 bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] rounded-full text-sm placeholder:text-zinc-400 focus:outline-none">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 opacity-30" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5 21 21"/></svg>
            </div>
        </form>

        <nav class="flex items-center gap-1.5 ml-auto">
            <a href="{{ route('web.checkin') }}" class="hidden sm:inline-flex h-8 px-3 items-center rounded-full text-sm font-medium hover:bg-[var(--paper2)] transition">签到</a>
            <a href="{{ route('web.pricing') }}" class="hidden sm:inline-flex h-8 px-3 items-center rounded-full text-sm font-medium hover:bg-[var(--paper2)] transition">套餐</a>
            <a href="{{ route('web.cart') }}" class="relative inline-flex h-9 px-4 items-center gap-2 rounded-full bg-[var(--paper2)] hover:bg-[#EEE8DD] border border-[var(--line)] text-sm font-medium transition">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 6h14l-1.5 8H6z"/><path d="M6 6 5 2H2"/><circle cx="9" cy="19" r="1.4"/><circle cx="18" cy="19" r="1.4"/></svg>
                购物车
                <span x-text="count" x-show="count>0" x-cloak class="min-w-5 h-5 grid place-items-center rounded-full bg-[var(--accent)] text-white text-xs px-1">0</span>
            </a>
            @auth
                <a href="{{ route('web.orders') }}" class="hidden sm:inline-flex h-9 px-4 items-center rounded-full bg-[var(--ink)] text-white text-sm font-medium hover:bg-black">订单</a>
                <div class="relative hidden md:inline-flex items-center" x-data="{open:false}" @mouseenter="open=true" @mouseleave="open=false">
                    <span class="mono text-xs opacity-50 max-w-[88px] truncate pl-1 pr-1 cursor-pointer inline-flex items-center gap-1">{{ auth()->user()->name }}<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="opacity-40"><path d="M6 9l6 6 6-6"/></svg></span>
                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute right-0 top-full mt-3 w-44 bg-white rounded-2xl border border-[var(--line)] soft overflow-hidden z-50">
                        <div class="p-2">
                            <a href="{{ route('web.me') }}" class="flex items-center gap-2 px-3 py-2 rounded-full hover:bg-[var(--paper2)] mono text-sm"><span class="w-6 h-6 rounded-full bg-[var(--paper2)] border border-[var(--line)] grid place-items-center text-xs">◉</span>个人中心</a>
                            <a href="{{ route('web.checkin') }}" class="flex items-center gap-2 px-3 py-2 rounded-full hover:bg-[var(--paper2)] mono text-sm"><span class="w-6 h-6 rounded-full bg-emerald-50 border border-emerald-200 grid place-items-center text-xs">✓</span>每日签到</a>
                            <a href="{{ route('web.orders') }}" class="flex items-center gap-2 px-3 py-2 rounded-full hover:bg-[var(--paper2)] mono text-sm sm:hidden"><span class="w-6 h-6 rounded-full bg-[var(--ink)] text-white grid place-items-center text-xs">≡</span>我的订单</a>
                            <div class="my-1 border-t border-[var(--line)]"></div>
                            <form method="POST" action="{{ route('web.logout') }}">@csrf<button class="w-full flex items-center gap-2 px-3 py-2 rounded-full hover:bg-red-50 mono text-sm text-red-600"><span class="w-6 h-6 rounded-full bg-red-50 border border-red-200 grid place-items-center text-xs">↗</span>退出登录</button></form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('web.login') }}" class="hidden sm:inline-flex h-9 px-4 items-center rounded-full border border-[var(--line)] bg-white text-sm font-medium hover:bg-[var(--paper2)]">登录</a>
                <a href="{{ route('web.register') }}" class="inline-flex h-9 px-5 items-center rounded-full bg-[var(--ink)] text-white text-sm font-semibold hover:bg-black">注册</a>
            @endauth
            <a href="/admin" data-admin-link class="js-admin-link hidden xl:inline-flex w-9 h-9 items-center justify-center rounded-full border border-[var(--line)] hover:bg-[var(--paper2)] text-zinc-500" title="商户后台">↗</a>
        </nav>
    </header>
</div>

<main class="flex-1 max-w-[1280px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    @if(session('success'))<div class="mb-4 mono text-sm px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 mono text-sm px-4 py-3 rounded-2xl bg-red-50 border border-red-200 text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 px-4 py-3 rounded-2xl bg-red-50 border border-red-200 mono text-sm"><ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>

<footer class="mt-12 border-t border-[var(--line)] bg-white/60">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row gap-8 justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-[var(--ink)] text-white grid place-items-center serif text-sm">MP</span>
                    <span class="serif text-lg">Membership Pro</span>
                    <span class="mono text-xs opacity-40">© {{ date('Y') }}</span>
                </div>
                <p class="mono text-xs leading-relaxed opacity-60 mt-2 max-w-[42ch]">30 秒开一家属于自己的品牌网店。独立域名 · 会员营销 · 全渠道收款 · 仅 5% 交易抽佣。</p>
            </div>
            <div class="grid grid-cols-3 gap-8 mono text-xs leading-6">
                <div><div class="font-semibold tracking-widest opacity-100 mb-1">开店</div><a href="/tenants/register" class="opacity-60 hover:opacity-100 underline">免费入驻</a><br><a href="/pricing" class="opacity-60 hover:opacity-100 underline">会员套餐</a><br><a href="/check-in" class="opacity-60 hover:opacity-100 underline">每日签到</a></div>
                <div><div class="font-semibold tracking-widest opacity-100 mb-1">我的</div><a href="/me" class="opacity-60 hover:opacity-100 underline">个人中心</a><br><a href="/orders" class="opacity-60 hover:opacity-100 underline">我的订单</a><br><a href="/cart" class="opacity-60 hover:opacity-100 underline">购物车</a></div>
                <div><div class="font-semibold tracking-widest opacity-100 mb-1">商家</div><a href="/admin" data-admin-link class="js-admin-link opacity-60 hover:opacity-100 underline">商家后台</a><br><span class="opacity-60">商家入驻协议</span><br><span class="opacity-60">隐私政策</span></div>
            </div>
        </div>
        <div class="mt-6 pt-4 border-t border-[var(--line)] flex flex-wrap gap-3 mono text-[11px] tracking-wide opacity-50 justify-between">
            <span>© {{ date('Y') }} Membership Pro · 30 秒开一家像样的网店</span>
            <span class="flex gap-3"><a href="/tenants/register" class="hover:opacity-100 underline font-semibold">免费开店 →</a></span>
        </div>
    </div>
</footer>

{{-- 权限弹窗（普通会员点击 /admin 不直接 403） --}}
<div x-data="{ open: false, title: '无权限访问', msg: '当前账号为普通会员，仅限商户管理员访问后台。' }"
     x-show="open" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @perm-denied.window="open=true; msg=$event.detail?.msg || msg; title=$event.detail?.title || title"
     @keydown.escape.window="open=false"
     class="fixed inset-0 z-[80] grid place-items-center p-4">
    <div x-show="open" x-transition @click="open=false" class="absolute inset-0 bg-[var(--ink)]/40 backdrop-blur-[2px]"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="relative w-full max-w-[480px] bg-white rounded-[28px] border border-[var(--line)] soft overflow-hidden">
        <div class="px-7 pt-7 pb-2 text-center">
            <div class="w-12 h-12 rounded-full bg-amber-50 border border-amber-200 grid place-items-center mx-auto">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
            </div>
            <h3 class="serif text-[24px] leading-none mt-3" x-text="title"></h3>
            <p class="mono text-[11px] tracking-[0.14em] opacity-40 mt-1">PERMISSION DENIED</p>
            <p class="text-sm leading-6 opacity-70 mt-3" x-text="msg"></p>
            @auth
            <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-xs">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ auth()->user()->email }}
            </div>
            @endauth
        </div>
        <div class="px-6 pb-6 pt-3">
            <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3.5 mono text-xs leading-5 opacity-70">
                <div class="font-semibold tracking-widest text-[11px] opacity-60 mb-1.5">如何解决</div>
                <ul class="list-disc ml-5 space-y-1">
                    <li>请使用店铺管理员账号登录 <a href="/admin" class="underline text-[var(--accent)]">/admin</a></li>
                    <li>普通会员请前往 <a href="/me" class="underline">个人中心</a> 或 <a href="/" class="underline">商城首页</a></li>
                    <li>需要开店？<a href="/tenants/register" class="underline text-[var(--accent)]">申请入驻</a></li>
                </ul>
            </div>
        </div>
        <div class="px-6 pb-6 flex gap-2.5">
            <button @click="open=false" class="flex-1 h-11 rounded-full bg-[var(--ink)] text-white text-sm font-semibold hover:bg-black transition">知道了</button>
            <a href="/me" class="inline-flex items-center justify-center h-11 px-6 rounded-full border border-[var(--line)] bg-white text-sm font-medium hover:bg-[var(--paper2)]">个人中心</a>
        </div>
    </div>
</div>

<script>
window.__canAccessAdmin = @json($__canAccessAdmin);
window.__isAuthed = @json(auth()->check());
document.addEventListener('DOMContentLoaded', function(){
    // 拦截所有 /admin 链接：普通会员弹窗而非直接跳转 403
    document.addEventListener('click', function(e){
        const a = e.target.closest('a.js-admin-link, a[data-admin-link], a[href^="/admin"]');
        if(!a) return;
        const href = a.getAttribute('href') || '';
        if(!href.startsWith('/admin')) return;
        if(window.__canAccessAdmin) return; // 有权限放行
        // 未登录：引导登录
        if(!window.__isAuthed){
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('perm-denied', {detail:{title:'请先登录', msg:'商户后台仅限已登录的商户管理员访问，请先登录或使用商户管理员账号。'}}));
            return;
        }
        // 普通会员：弹窗拦截
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('perm-denied', {detail:{title:'无权限访问', msg:'当前账号为普通会员，仅限商户管理员访问后台。如需管理店铺，请联系商户管理员或申请入驻。'}}));
    }, true);

    // 若后端闪存 show_perm_modal 或 error 含权限关键字，自动弹窗（用于直接访问 /admin 被重定向回来的兜底）
    @if(session('show_perm_modal') || (session('error') && str_contains(session('error'), '权限')))
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('perm-denied', {detail:{title:'无权限访问', msg:@json(session('error') ?: '当前账号无后台权限，已拦截 403 并以弹窗提示。')}}));
        });
    @endif

    // 全局 fetch 403 兜底：API 返回 403 时也弹窗
    const _fetch = window.fetch;
    window.fetch = function(input, init){
        return _fetch(input, init).then(res=>{
            if(res.status===403){
                const url = typeof input==='string'?input:input.url||'';
                if(url.includes('/admin') || url.includes('/api')){
                    window.dispatchEvent(new CustomEvent('perm-denied', {detail:{title:'无权限访问', msg:'请求被拒绝（403）：当前账号无该操作权限。'}}));
                }
            }
            return res;
        });
    };
});
</script>

<script>
function cartStore(){
    return {
        items: [],
        get count(){ return this.items.reduce((s,i)=>s+i.quantity,0) },
        init(){ try{ this.items = JSON.parse(localStorage.getItem('cart')||'[]') }catch(e){ this.items=[] } },
        save(){ localStorage.setItem('cart', JSON.stringify(this.items)) },
        add(p){ let f=this.items.find(i=>i.id===p.id); if(f) f.quantity++; else this.items.push({id:p.id, name:p.name, price:parseFloat(p.price), cover:p.cover, shop_id:p.shop_id, shop_name:p.shop_name||'', quantity:1}); this.save() },
        remove(id){ this.items=this.items.filter(i=>i.id!==id); this.save() },
        clear(){ this.items=[]; this.save() }
    }
}
</script>
@stack('scripts')
</body>
</html>
