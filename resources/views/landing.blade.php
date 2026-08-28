@extends('layouts.shop')
@section('title','Membership Pro — 多商户电商SaaS · shop1.xxx.com')
@section('content')

{{-- HERO --}}
<section class="relative overflow-hidden rounded-[32px] bg-white border border-[var(--line)] soft">
    {{-- gradient blobs --}}
    <div class="pointer-events-none absolute -top-24 -right-24 w-[560px] h-[560px] rounded-full opacity-[0.12]" style="background:radial-gradient(circle at 30% 30%, #FF6B35 0%, #FFD6A8 40%, transparent 70%)"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-32 w-[640px] h-[640px] rounded-full opacity-[0.10]" style="background:radial-gradient(circle at 50% 50%, #6B7F72 0%, #C8D6C2 45%, transparent 70%)"></div>

    <div class="relative grid lg:grid-cols-[1.05fr_0.95fr] gap-0">
        {{-- left copy --}}
        <div class="px-6 sm:px-10 lg:px-12 py-10 sm:py-12 lg:py-14 flex flex-col justify-center">
            <div class="inline-flex items-center gap-2 self-start px-3 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-wide">
                <span class="w-2 h-2 rounded-full bg-[var(--accent)] animate-pulse"></span>
                SaaS + 电商复合 · CodeCanyon 就绪 — 已跑通 Filament 5.7
            </div>
            <h1 class="serif text-[36px] sm:text-[52px] lg:text-[56px] leading-[0.88] tracking-[-0.04em] mt-5">
                一个平台，<br>
                开<span class="relative inline-block">千家
                    <span class="absolute left-0 right-0 bottom-1 h-[10px] bg-[#FFE4D6] -z-10 rounded-full"></span>
                </span><span class="news italic font-light text-[1.05em]">不撞衫</span>的店
            </h1>
            <p class="mt-4 text-[15px] leading-7 text-zinc-600 max-w-[48ch]">
                复用现有会员体系，<span class="font-semibold text-[var(--ink)]">shop1.xxx.com</span> 子域隔离。商户自助入驻 → 独立店铺 → 商品上架 → 订单抽佣 5% → 钱包 / 微信 / 支付宝 / Stripe 闭环。
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="/tenants/register" class="h-11 px-6 inline-flex items-center gap-2 rounded-full bg-[var(--ink)] text-white font-semibold text-sm hover:bg-black transition soft">免费创建店铺 — 30秒开张 <span>→</span></a>
                <a href="/shop/demo" class="h-11 px-5 inline-flex items-center gap-2 rounded-full bg-white border border-[var(--line)] font-medium text-sm hover:bg-[var(--paper2)]">看演示店 <span class="mono text-xs opacity-50">demo.xxx.com</span></a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3 mono text-[11px]">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ Sanctum 已验证</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ 自动分账</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ 单店校验</span>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-3 max-w-[480px]">
                <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">商户</div><div class="serif text-xl leading-none mt-1">∞</div><div class="mono text-[11px] opacity-60">slug → domain</div>
                </div>
                <div class="rounded-2xl bg-[var(--ink)] text-white p-3">
                    <div class="mono text-[10px] tracking-widest opacity-60">抽佣</div><div class="serif text-xl leading-none mt-1">5%</div><div class="mono text-[11px] opacity-60">自动计提</div>
                </div>
                <div class="rounded-2xl bg-white border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">支付</div><div class="serif text-xl leading-none mt-1">6</div><div class="mono text-[11px] opacity-60">通道</div>
                </div>
            </div>
        </div>

        {{-- right: browser preview --}}
        <div class="relative bg-[var(--paper2)] lg:border-l border-t lg:border-t-0 border-[var(--line)] p-6 sm:p-8 flex flex-col justify-center" x-data="{ slug:'summer', get domain(){ return (this.slug||'shop1')+'.xxx.com' } }">
            <div class="mono text-[11px] tracking-[0.16em] opacity-50 flex items-center justify-between mb-3">
                <span>LIVE PREVIEW — 输入即所得</span><span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            {{-- browser chrome --}}
            <div class="bg-white rounded-[20px] border border-[var(--line)] soft overflow-hidden">
                <div class="h-9 flex items-center gap-1.5 px-4 border-b border-[var(--line)] bg-[#FCFCFA]">
                    <span class="w-3 h-3 rounded-full bg-[#FF5F56] border border-black/5"></span><span class="w-3 h-3 rounded-full bg-[#FFBD2E] border border-black/5"></span><span class="w-3 h-3 rounded-full bg-[#27C93F] border border-black/5"></span>
                    <span class="ml-3 flex-1 h-7 rounded-full bg-[var(--paper2)] border border-[var(--line)] flex items-center px-3 gap-2 mono text-xs">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span x-text="domain" class="font-medium"></span>
                        <span class="ml-auto opacity-40 hidden sm:inline">shop1.xxx.com 隔离</span>
                    </span>
                </div>
                <div class="p-5">
                    {{-- storefront mock --}}
                    <div class="rounded-2xl border border-[var(--line)] overflow-hidden">
                        <div class="h-28 bg-gradient-to-br from-[#FFF1E6] via-[#FFE4D6] to-[#F0E8DD] relative p-4 flex flex-col justify-end">
                            <div class="absolute top-3 right-3 mono text-[10px] tracking-widest bg-white/80 backdrop-blur px-2 py-1 rounded-full border border-white">OPEN · 演示旗舰店</div>
                            <div class="serif text-2xl leading-none" x-text="(slug||'summer')"></div>
                            <div class="mono text-xs opacity-60" x-text="domain"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 p-3 bg-white">
                            <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)]"></div>
                            <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)]"></div>
                            <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)] relative overflow-hidden">
                                <span class="absolute inset-0 grid place-items-center mono text-[10px] opacity-40">+3 more</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <div class="flex-1 relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 mono text-xs opacity-40">slug</span>
                            <input x-model="slug" @input="slug=$event.target.value.toLowerCase().replace(/[^a-z0-9-]/g,'').slice(0,16)" placeholder="summer" maxlength="16" class="w-full h-10 pl-12 pr-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] mono text-sm font-medium focus:outline-none">
                        </div>
                        <button @click="slug='demo-'+Math.floor(Math.random()*90+10)" class="h-10 px-4 rounded-full border border-[var(--line)] bg-white mono text-xs font-semibold hover:bg-[var(--paper2)]">随机</button>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a :href="'/shop/'+(slug||'demo')" class="h-10 rounded-full bg-[var(--ink)] text-white grid place-items-center mono text-xs font-semibold hover:bg-black">预览此店 →</a>
                        <a href="/tenants/register" class="h-10 rounded-full bg-[var(--accent)] text-white grid place-items-center mono text-xs font-semibold hover:bg-[#E63600]">去创建 ↗</a>
                    </div>
                    <div class="mt-3 mono text-[11px] leading-relaxed opacity-50 text-center">可用 a-z 0-9 - · 写入 tenants.slug 唯一索引 · 本地可走 /shop/<span x-text="slug||'demo'"></span></div>
                </div>
            </div>
            <div class="mt-3 mono text-[11px] opacity-40 text-center">Resolve: X-Tenant-Slug → shop1.xxx.com → /shop/{slug}</div>
        </div>
    </div>
    <div class="h-10 flex items-center gap-3 px-6 mono text-[11px] tracking-wide border-t border-[var(--line)] bg-[var(--paper2)]/60">
        <span class="font-semibold">下单闭环</span><span class="opacity-30">—</span><span>扣库存 → 创订单 ORD-xxx → Payment(business_type=order) → handleOrderPaid() 销量++</span>
        <span class="ml-auto hidden md:inline-flex items-center gap-2 opacity-60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> test_order.php 已验证</span>
    </div>
</section>

{{-- features --}}
<section class="mt-6 grid md:grid-cols-3 gap-4">
    <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
        <div class="w-10 h-10 rounded-full bg-[var(--paper2)] border border-[var(--line)] grid place-items-center">◈</div>
        <h3 class="font-semibold mt-4">B 端自助入驻</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-1">POST /api/v1/tenants/register 一键生成 Tenant / Branch / MembershipLevel / tenant_admin。</p>
        <code class="mt-3 block mono text-xs bg-[var(--ink)] text-white px-3 py-2 rounded-xl">{"slug":"shop1"} → shop1.xxx.com</code>
    </div>
    <div class="rounded-[24px] bg-[var(--ink)] text-white p-6 soft">
        <div class="w-10 h-10 rounded-full bg-white/10 border border-white/10 grid place-items-center">⬢</div>
        <h3 class="font-semibold mt-4">电商三表</h3>
        <p class="mono text-xs leading-relaxed opacity-70 mt-1">shops(tenant_id, slug, platform_fee_rate) / products / orders+order_items。Tenant→shops/orders 关联。</p>
        <div class="mt-3 mono text-xs bg-white/10 border border-white/10 px-3 py-2 rounded-xl">扣库存 → 创订单 → PaymentService</div>
    </div>
    <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
        <div class="w-10 h-10 rounded-full bg-[#FFE4D6] border border-[#FFD6B8] grid place-items-center text-[var(--accent)]">◎</div>
        <h3 class="font-semibold mt-4">支付与分账</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-1">mock / wallet / wechat / alipay / stripe / manual。PaymentService::handleOrderPaid() 自动分账。</p>
        <div class="mt-3 inline-flex mono text-xs px-2.5 py-1 rounded-full bg-[var(--accent)] text-white">5% 抽佣 · platform_fee</div>
    </div>
</section>

@if($plans->count())
<section class="mt-6 rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
    <div class="flex flex-wrap items-end justify-between gap-4 p-6 sm:p-8 border-b border-[var(--line)]">
        <div>
            <div class="mono text-[11px] tracking-[0.18em] opacity-50">PRICING — 会员套餐</div>
            <h2 class="serif text-2xl sm:text-3xl tracking-[-0.02em] mt-1">复用 MembershipPlan，<span class="news italic font-light">与商城同仓</span></h2>
            <p class="mono text-xs opacity-50 mt-1">月卡 19.9 / 年卡 199 / 终身 599 · FREE_SHIPPING / COUPON_PACK / DISCOUNT</p>
        </div>
        <a href="{{ route('web.pricing') }}" class="mono text-xs font-semibold px-4 py-2 rounded-full bg-[var(--ink)] text-white hover:bg-black">完整套餐表 →</a>
    </div>
    <div class="grid md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[var(--line)]">
        @foreach($plans as $plan)
        <div class="p-6 flex flex-col {{ $plan->is_recommended ? 'bg-[var(--paper2)]' : 'bg-white' }}">
            @if($plan->is_recommended)<div class="mono text-[10px] tracking-[0.18em] bg-[var(--accent)] text-white px-2.5 py-1 rounded-full self-start">★ 最受欢迎</div>@endif
            <div class="font-semibold text-sm mt-3">{{ $plan->name }}</div>
            <div class="mt-1 flex items-baseline gap-2"><span class="serif text-3xl leading-none">¥{{ rtrim(rtrim(number_format($plan->price,2), '0'),'.') }}</span><span class="mono text-xs opacity-50">/ {{ $plan->billing_cycle }}</span></div>
            @if($plan->original_price)<div class="mono text-xs line-through opacity-30">原价 ¥{{ $plan->original_price }}</div>@endif
            <ul class="mt-4 mono text-xs leading-6 opacity-60 flex-1 border-t border-dashed border-[var(--line)] pt-3 space-y-0.5">
                @foreach($plan->benefits ?? [] as $b)<li>· {{ $b }}</li>@endforeach
                @foreach($plan->features as $f)<li>· {{ $f->name }} @if($f->pivot->quota) <span class="opacity-40">×{{ $f->pivot->quota }}</span>@endif</li>@endforeach
            </ul>
            <div class="mt-4 mono text-[11px] tracking-widest opacity-30 text-center">SKU-{{ str_pad($plan->id,4,'0',STR_PAD_LEFT) }}</div>
        </div>
        @endforeach
    </div>
</section>
@endif

<div class="mt-6 rounded-2xl border border-[var(--line)] bg-white p-4 flex flex-col sm:flex-row gap-4 justify-between mono text-xs soft">
    <div><span class="font-semibold">本地演示：</span> <code class="px-1.5 py-0.5 rounded bg-[var(--paper2)] border border-[var(--line)]">http://demo.xxx.com:8000</code> 或 <code class="px-1.5 py-0.5 rounded bg-[var(--paper2)] border border-[var(--line)]">/shop/demo</code><div class="opacity-60 mt-1">demo@member.com / 12345678 · tenant@demo.com / 12345678</div></div>
    <div class="flex gap-2 shrink-0 self-start"><a href="/shop/demo" class="h-9 px-4 rounded-full bg-[var(--ink)] text-white grid place-items-center font-semibold">进入 demo 店</a><a href="/tenants/register" class="h-9 px-4 rounded-full border border-[var(--line)] bg-white grid place-items-center font-semibold">创建新店</a></div>
</div>
@endsection
