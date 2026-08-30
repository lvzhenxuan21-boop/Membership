@extends('layouts.shop')
@section('title','30 秒开一家像样的网店 — Membership Pro 多商户开店平台')
@section('metaDescription','免费开通属于你自己的品牌网店：独立域名、会员积分储值体系、微信/支付宝/Stripe 全渠道收款，仅 5% 交易抽佣，不懂技术也能卖货。')

@section('content')

{{-- HERO --}}
<section class="relative overflow-hidden rounded-[32px] bg-white border border-[var(--line)] soft">
    <div class="pointer-events-none absolute -top-24 -right-24 w-[560px] h-[560px] rounded-full opacity-[0.12]" style="background:radial-gradient(circle at 30% 30%, #FF6B35 0%, #FFD6A8 40%, transparent 70%)"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-32 w-[640px] h-[640px] rounded-full opacity-[0.10]" style="background:radial-gradient(circle at 50% 50%, #6B7F72 0%, #C8D6C2 45%, transparent 70%)"></div>

    <div class="relative grid lg:grid-cols-[1.05fr_0.95fr] gap-0">
        {{-- left copy --}}
        <div class="px-6 sm:px-10 lg:px-12 py-10 sm:py-12 lg:py-14 flex flex-col justify-center">
            <div class="inline-flex items-center gap-2 self-start px-3 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] mono text-[11px] tracking-wide">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                @if($storeCount > 0){{ $storeCount }} 家店铺正在营业@else首批商户招募中@endif · 平台已上线
            </div>
            <h1 class="serif text-[36px] sm:text-[52px] lg:text-[56px] leading-[0.92] tracking-[-0.04em] mt-5">
                30 秒，<br>
                开一家<span class="relative inline-block">像样
                    <span class="absolute left-0 right-0 bottom-1 h-[10px] bg-[#FFE4D6] -z-10 rounded-full"></span>
                </span>的网店
            </h1>
            <p class="mt-4 text-[15px] leading-7 text-zinc-600 max-w-[46ch]">
                注册即得独立域名的品牌店铺（<span class="font-semibold text-[var(--ink)]">summer.xxx.com</span>），会员、储值、优惠券后台一键配好，微信 / 支付宝 / Stripe 直接收款。<span class="font-semibold text-[var(--ink)]">不懂技术也能卖货。</span>
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="/tenants/register" class="h-11 px-6 inline-flex items-center gap-2 rounded-full bg-[var(--ink)] text-white font-semibold text-sm hover:bg-black transition soft">免费开店 — 30 秒开张 <span>→</span></a>
                @if($demoUrl)<a href="{{ $demoUrl }}" class="h-11 px-5 inline-flex items-center gap-2 rounded-full bg-white border border-[var(--line)] font-medium text-sm hover:bg-[var(--paper2)]">先逛逛示例店铺 <span class="mono text-xs opacity-50">{{ $demoLabel }}</span></a>@endif
            </div>
            <div class="mt-6 flex flex-wrap gap-3 mono text-[11px]">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ 仅 5% 交易抽佣</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ 0 年费 · 0 套餐费</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[var(--line)]">✓ 货款自动结算</span>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-3 max-w-[480px]">
                <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">开店成本</div><div class="serif text-xl leading-none mt-1">¥0</div><div class="mono text-[11px] opacity-60">无月费年费</div>
                </div>
                <div class="rounded-2xl bg-[var(--ink)] text-white p-3">
                    <div class="mono text-[10px] tracking-widest opacity-60">交易抽佣</div><div class="serif text-xl leading-none mt-1">5%</div><div class="mono text-[11px] opacity-60">不成交不收费</div>
                </div>
                <div class="rounded-2xl bg-white border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">开张时间</div><div class="serif text-xl leading-none mt-1">30<span class="text-sm">秒</span></div><div class="mono text-[11px] opacity-60">从注册到开店</div>
                </div>
            </div>
        </div>

        {{-- right: live preview --}}
        <div class="relative bg-[var(--paper2)] lg:border-l border-t lg:border-t-0 border-[var(--line)] p-6 sm:p-8 flex flex-col justify-center" x-data="{ slug:'summer', get domain(){ return (this.slug||'shop1')+'.xxx.com' } }">
            <div class="mono text-[11px] tracking-[0.16em] opacity-50 flex items-center justify-between mb-3">
                <span>试试你的店名 — 输入即所得</span><span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <div class="bg-white rounded-[20px] border border-[var(--line)] soft overflow-hidden">
                <div class="h-9 flex items-center gap-1.5 px-4 border-b border-[var(--line)] bg-[#FCFCFA]">
                    <span class="w-3 h-3 rounded-full bg-[#FF5F56] border border-black/5"></span><span class="w-3 h-3 rounded-full bg-[#FFBD2E] border border-black/5"></span><span class="w-3 h-3 rounded-full bg-[#27C93F] border border-black/5"></span>
                    <span class="ml-3 flex-1 h-7 rounded-full bg-[var(--paper2)] border border-[var(--line)] flex items-center px-3 gap-2 mono text-xs">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span x-text="domain" class="font-medium"></span>
                        <span class="ml-auto opacity-40 hidden sm:inline">你的独立店铺地址</span>
                    </span>
                </div>
                <div class="p-5">
                    <div class="rounded-2xl border border-[var(--line)] overflow-hidden">
                        <div class="h-28 bg-gradient-to-br from-[#FFF1E6] via-[#FFE4D6] to-[#F0E8DD] relative p-4 flex flex-col justify-end">
                            <div class="absolute top-3 right-3 mono text-[10px] tracking-widest bg-white/80 backdrop-blur px-2 py-1 rounded-full border border-white">营业中 · 第 {{ $storeCount + 1 }} 家</div>
                            <div class="serif text-2xl leading-none" x-text="(slug||'summer')"></div>
                            <div class="mono text-xs opacity-60" x-text="domain"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 p-3 bg-white">
                            @forelse($samples as $sp)
                                <a href="/products/{{ $sp->id }}" class="block aspect-[4/3] rounded-xl overflow-hidden border border-[var(--line)] relative group">
                                    <img src="{{ $sp->cover }}" alt="{{ $sp->name }}" class="w-full h-full object-cover group-hover:scale-[1.06] transition duration-500">
                                    <span class="absolute bottom-0 left-0 right-0 mono text-[9px] text-center bg-white/80 backdrop-blur py-0.5 truncate">{{ $sp->name }}</span>
                                </a>
                            @empty
                                <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)]"></div>
                                <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)]"></div>
                                <div class="aspect-[4/3] rounded-xl bg-[var(--paper2)] border border-[var(--line)] relative overflow-hidden">
                                    <span class="absolute inset-0 grid place-items-center mono text-[10px] opacity-40">你的商品</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <div class="flex-1 relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 mono text-xs opacity-40">店名</span>
                            <input x-model="slug" @input="slug=$event.target.value.toLowerCase().replace(/[^a-z0-9-]/g,'').slice(0,16)" placeholder="summer" maxlength="16" class="w-full h-10 pl-14 pr-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] mono text-sm font-medium focus:outline-none">
                        </div>
                        <button @click="slug='demo-'+Math.floor(Math.random()*90+10)" class="h-10 px-4 rounded-full border border-[var(--line)] bg-white mono text-xs font-semibold hover:bg-[var(--paper2)]">随机</button>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a href="/shop/demo" :href="'/shop/'+(slug||'demo')" class="h-10 rounded-full bg-[var(--ink)] text-white grid place-items-center mono text-xs font-semibold hover:bg-black">预览此店 →</a>
                        <a href="/tenants/register" :href="'/tenants/register?slug='+(slug||'')" class="h-10 rounded-full bg-[var(--accent)] text-white grid place-items-center mono text-xs font-semibold hover:bg-[#E63600]">用这个名字开店 ↗</a>
                    </div>
                    <div class="mt-3 mono text-[11px] leading-relaxed opacity-50 text-center">选个好记的名字，这就是你的店址，开通后立刻可分享</div>
                </div>
            </div>
            <div class="mt-3 mono text-[11px] opacity-40 text-center">每家店独立域名 · 数据完全隔离 · 互不干扰</div>
        </div>
    </div>
    <div class="h-10 flex items-center gap-3 px-6 mono text-[11px] tracking-wide border-t border-[var(--line)] bg-[var(--paper2)]/60">
        <span class="font-semibold">开店三步</span><span class="opacity-30">—</span><span>注册账号 → 装修店铺上架商品 → 分享店铺链接开始接单</span>
        <span class="ml-auto hidden md:inline-flex items-center gap-2 opacity-60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 收款结算全自动</span>
    </div>
</section>

{{-- 三步开店 --}}
<section class="mt-6 grid md:grid-cols-3 gap-4">
    <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
        <div class="serif text-3xl text-[#FFD6B8] leading-none">01</div>
        <h3 class="font-semibold mt-3">注册账号</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-1">一个邮箱 30 秒完成，立刻拥有你的店铺地址和可视化经营后台。</p>
    </div>
    <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
        <div class="serif text-3xl text-[#FFD6B8] leading-none">02</div>
        <h3 class="font-semibold mt-3">装修店铺 · 上架商品</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-1">改店名、换门头、传商品图，会员折扣 / 储值 / 优惠券后台点两下就配好。</p>
    </div>
    <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
        <div class="serif text-3xl text-[#FFD6B8] leading-none">03</div>
        <h3 class="font-semibold mt-3">分享链接 · 开始接单</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-1">把店铺链接发到朋友圈、社群，买家下单自动收款，货款结算到你的店铺钱包。</p>
    </div>
</section>

{{-- 你能得到什么 --}}
<section class="mt-6">
    <div class="text-center max-w-2xl mx-auto">
        <div class="mono text-[11px] tracking-[0.18em] opacity-40">WHAT YOU GET</div>
        <h2 class="serif text-2xl sm:text-3xl tracking-[-0.02em] mt-1">开一家店，<span class="news italic font-light">这些全都配齐</span></h2>
    </div>
    <div class="mt-6 grid sm:grid-cols-2 gap-4">
        <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
            <h3 class="font-semibold">独立品牌店铺</h3>
            <p class="mono text-xs leading-relaxed opacity-60 mt-1">自己的店名和域名，门头自定义，顾客记住的是你的品牌，不是平台的。</p>
            <div class="mt-4 rounded-2xl border border-[var(--line)] overflow-hidden">
                <div class="h-7 flex items-center gap-1.5 px-3 bg-[#FCFCFA] border-b border-[var(--line)]">
                    <span class="w-2 h-2 rounded-full bg-[#FF5F56]"></span><span class="w-2 h-2 rounded-full bg-[#FFBD2E]"></span><span class="w-2 h-2 rounded-full bg-[#27C93F]"></span>
                    <span class="ml-2 mono text-[10px] opacity-50">yourshop.xxx.com</span>
                </div>
                <div class="h-12 bg-gradient-to-r from-[#FFF1E6] to-[#FFE4D6] flex items-center px-3"><span class="serif text-lg">你的店</span></div>
            </div>
        </div>
        <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
            <h3 class="font-semibold">会员营销全套</h3>
            <p class="mono text-xs leading-relaxed opacity-60 mt-1">会员等级折扣、积分、储值余额、优惠券——后台点两下就配好，复购率交给系统。</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[var(--ink)] text-white">金卡 88 折</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[#FFE4D6] border border-[#FFD6B8] text-[var(--accent)] font-semibold">积分 ×2</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[var(--paper2)] border border-[var(--line)]">储值 ¥200 送 20</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[var(--paper2)] border border-[var(--line)]">新人券包</span>
            </div>
        </div>
        <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
            <h3 class="font-semibold">全渠道收款</h3>
            <p class="mono text-xs leading-relaxed opacity-60 mt-1">微信、支付宝、Stripe 境外卡、余额支付，顾客怎么方便怎么来，货款自动结算。</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[#E8F5E9] border border-[#C8E6C9] text-[#1B5E20] font-medium">微信支付</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[#E8F0FE] border border-[#C5D8F7] text-[#1A47B8] font-medium">支付宝</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[#F3E8FF] border border-[#E0C7FB] text-[#6B21A8] font-medium">Stripe 境外卡</span>
                <span class="mono text-[11px] px-3 py-1.5 rounded-full bg-[var(--paper2)] border border-[var(--line)]">余额</span>
            </div>
        </div>
        <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 soft">
            <h3 class="font-semibold">订单与数据看板</h3>
            <p class="mono text-xs leading-relaxed opacity-60 mt-1">销量、库存、会员增长实时可见，谁的店卖得好，数字说了算。</p>
            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-[var(--paper2)] border border-[var(--line)] p-2.5"><div class="mono text-[9px] tracking-widest opacity-50">今日订单</div><div class="serif text-lg leading-none mt-1">128</div></div>
                <div class="rounded-xl bg-[var(--paper2)] border border-[var(--line)] p-2.5"><div class="mono text-[9px] tracking-widest opacity-50">本月会员</div><div class="serif text-lg leading-none mt-1">+36</div></div>
                <div class="rounded-xl bg-[var(--ink)] text-white p-2.5"><div class="mono text-[9px] tracking-widest opacity-60">销售额</div><div class="serif text-lg leading-none mt-1">¥8.6k</div></div>
            </div>
        </div>
    </div>
</section>

{{-- 在营店铺墙 --}}
<section class="mt-6 rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
    <div class="flex flex-wrap items-end justify-between gap-4 p-6 sm:p-8 border-b border-[var(--line)]">
        <div>
            <div class="mono text-[11px] tracking-[0.18em] opacity-50">REAL STORES</div>
            <h2 class="serif text-2xl sm:text-3xl tracking-[-0.02em] mt-1">他们已经<span class="news italic font-light">开张了</span></h2>
            <p class="mono text-xs opacity-50 mt-1">每一家都是独立域名的品牌店，点进去逛逛</p>
        </div>
        <span class="mono text-xs px-4 py-2 rounded-full bg-[var(--paper2)] border border-[var(--line)]">共 {{ $storeCount }} 家在营</span>
    </div>
    <div class="p-6 sm:p-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @forelse($stores as $s)
            <a href="/shop/{{ $s->slug }}" class="group rounded-2xl border border-[var(--line)] overflow-hidden hover:soft-hover transition bg-white">
                <div class="h-20 bg-gradient-to-br from-[#FFF1E6] via-[#FFE4D6] to-[#F0E8DD] flex items-end p-3">
                    <span class="serif text-xl leading-none">{{ \Illuminate\Support\Str::limit($s->name, 8) }}</span>
                </div>
                <div class="p-3">
                    <div class="mono text-xs font-semibold group-hover:text-[var(--accent)] transition">{{ $s->slug }}.xxx.com</div>
                    <div class="mono text-[10px] opacity-40 mt-1">{{ $s->created_at->format('Y-m-d') }} 开店</div>
                    <div class="mono text-[11px] mt-2 text-[var(--accent)] font-semibold">逛逛 →</div>
                </div>
            </a>
        @empty
            <a href="/shop/demo" class="rounded-2xl border border-[var(--line)] overflow-hidden bg-white">
                <div class="h-20 bg-gradient-to-br from-[#FFF1E6] to-[#F0E8DD] flex items-end p-3"><span class="serif text-xl leading-none">演示店</span></div>
                <div class="p-3"><div class="mono text-xs font-semibold">demo.xxx.com</div><div class="mono text-[11px] mt-2 text-[var(--accent)] font-semibold">逛逛 →</div></div>
            </a>
        @endforelse
    </div>
    <div class="px-6 sm:px-8 pb-6">
        <a href="/tenants/register" class="h-11 px-6 inline-flex items-center gap-2 rounded-full bg-[var(--ink)] text-white font-semibold text-sm hover:bg-black soft">我也要开一家 →</a>
    </div>
</section>

{{-- 收费 --}}
<section class="mt-6 rounded-[24px] bg-[var(--ink)] text-white p-8 sm:p-10 soft">
    <div class="mono text-[11px] tracking-[0.18em] opacity-60">PRICING — 收费明明白白</div>
    <h2 class="serif text-2xl sm:text-3xl tracking-[-0.02em] mt-2">不成交，<span class="news italic font-light">一分钱不收</span></h2>
    <div class="mt-6 grid sm:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white/5 border border-white/10 p-5">
            <div class="serif text-3xl leading-none">¥0</div>
            <div class="mono text-xs mt-2 opacity-70">月费 / 年费 / 开店费</div>
        </div>
        <div class="rounded-2xl bg-white/5 border border-white/10 p-5">
            <div class="serif text-3xl leading-none">¥0</div>
            <div class="mono text-xs mt-2 opacity-70">套餐费 / 会员功能费</div>
        </div>
        <div class="rounded-2xl bg-[var(--accent)] p-5">
            <div class="serif text-3xl leading-none">5%</div>
            <div class="mono text-xs mt-2 opacity-80">仅交易抽佣 · 你卖货我才赚</div>
        </div>
    </div>
    <p class="mono text-xs leading-relaxed opacity-60 mt-5">买家付款后，货款自动结算到你的店铺钱包；没有成交，就没有任何费用。</p>
</section>

{{-- FAQ --}}
<section class="mt-6 grid md:grid-cols-2 gap-3">
    <div class="rounded-2xl bg-white border border-[var(--line)] p-5">
        <h3 class="font-semibold text-sm">我不懂技术，能用吗？</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-2">能。注册到开店全程可视化操作，商品上架、改价格、配会员折扣都在后台点两下完成，30 秒开张。</p>
    </div>
    <div class="rounded-2xl bg-white border border-[var(--line)] p-5">
        <h3 class="font-semibold text-sm">货款怎么结算？</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-2">买家付款后，货款自动结算到你店铺的钱包，随时查账。平台只在成交时收 5% 交易服务费。</p>
    </div>
    <div class="rounded-2xl bg-white border border-[var(--line)] p-5">
        <h3 class="font-semibold text-sm">会员卡、储值、积分这些能用吗？</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-2">后台一键开启：等级折扣、积分、储值充值、优惠券都是现成的，不需要额外开发。</p>
    </div>
    <div class="rounded-2xl bg-white border border-[var(--line)] p-5">
        <h3 class="font-semibold text-sm">我的店铺数据和别家隔离吗？</h3>
        <p class="mono text-xs leading-relaxed opacity-60 mt-2">完全隔离。每家店独立域名、独立数据，你的会员和订单只有你能看到。</p>
    </div>
</section>

{{-- 底部 CTA + Footer --}}
<section class="mt-6 rounded-[24px] bg-white border border-[var(--line)] p-8 text-center soft">
    <h2 class="serif text-2xl sm:text-3xl tracking-[-0.02em]">下一个开张的，<span class="news italic font-light">是你的店</span></h2>
    <a href="/tenants/register" class="mt-5 h-12 px-8 inline-flex items-center gap-2 rounded-full bg-[var(--accent)] text-white font-semibold hover:bg-[#E63600] soft">免费开店 — 30 秒开张 →</a>
    <div class="mono text-[11px] opacity-40 mt-4">不需要绑定银行卡 · 不成交不收费 · 随时可以停用</div>
</section>
@endsection
