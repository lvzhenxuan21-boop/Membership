@extends('layouts.shop')
@section('title', $tenant->name.' — 商城')
@section('content')
<div class="flex flex-col lg:flex-row gap-6">
    <aside class="lg:w-[280px] shrink-0">
        <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft sticky top-[76px]">
            <div class="px-5 pt-5 pb-4">
                <div class="mono text-[11px] tracking-[0.16em] opacity-40">STORE DIRECTORY</div>
                <div class="serif text-xl leading-none mt-1">{{ $tenant->name }}</div>
                <div class="mono text-xs opacity-50 mt-1">{{ $tenant->slug }}.xxx.com · {{ $shops->count() }} 家店铺</div>
            </div>
            <div class="px-3 pb-3 space-y-1.5">
                <a href="{{ url('/') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-full text-sm font-medium transition {{ !request('shop_id') ? 'bg-[var(--ink)] text-white' : 'bg-[var(--paper2)] hover:bg-white border border-[var(--line)]' }}">
                    <span class="w-7 h-7 rounded-full grid place-items-center text-xs font-bold {{ !request('shop_id') ? 'bg-white text-[var(--ink)]' : 'bg-white border border-[var(--line)]' }}">∞</span>
                    全部商品 <span class="ml-auto mono text-xs opacity-50">{{ $products->total() }}</span>
                </a>
                @foreach($shops as $shop)
                    <a href="{{ url('/').'?shop_id='.$shop->id }}" class="flex items-center gap-3 px-3 py-2.5 rounded-full text-sm transition {{ (string)request('shop_id')===(string)$shop->id ? 'bg-[var(--ink)] text-white' : 'bg-white border border-[var(--line)] hover:bg-[var(--paper2)]' }}">
                        <span class="w-7 h-7 rounded-full grid place-items-center mono text-xs font-bold {{ (string)request('shop_id')===(string)$shop->id ? 'bg-white text-[var(--ink)]' : 'bg-[var(--paper2)]' }}">{{ Str::upper(Str::substr($shop->name,0,1)) }}</span>
                        <span class="flex-1 truncate">{{ $shop->name }}</span><span class="mono text-xs opacity-50">{{ $shop->platform_fee_rate*100 }}%</span>
                    </a>
                @endforeach
            </div>
            <div class="m-3 rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3 mono text-xs leading-relaxed">
                <div class="font-semibold">提示</div>
                <span class="opacity-60">拣货篮单次仅支持同一店铺结算，抽佣按店铺自动计提。</span>
            </div>
        </div>
    </aside>

    <section class="flex-1 min-w-0">
        <div class="flex flex-wrap gap-3 items-center">
            <h1 class="serif text-2xl tracking-[-0.02em]">{{ request('shop_id') ? ($shops->firstWhere('id', request('shop_id'))->name ?? '店铺') : '全场精选' }}</h1>
            <span class="mono text-xs px-2.5 py-1 rounded-full bg-white border border-[var(--line)] opacity-60">共 {{ $products->total() }} 件 · {{ $products->lastPage() }} 页</span>
            <form method="GET" class="ml-auto flex gap-2">
                @if(request('shop_id'))<input type="hidden" name="shop_id" value="{{ request('shop_id') }}">@endif
                <input name="keyword" value="{{ request('keyword') }}" placeholder="店内搜索…" class="h-9 w-40 sm:w-64 px-4 rounded-full bg-white border border-[var(--line)] mono text-sm focus:outline-none focus:border-[var(--ink)]/20">
                <button class="h-9 px-5 rounded-full bg-[var(--ink)] text-white mono text-xs font-semibold hover:bg-black">搜索</button>
            </form>
        </div>

        @if($products->total()==0)
            <div class="mt-6 rounded-[24px] bg-white border border-dashed border-[var(--line)] p-12 text-center soft">
                <div class="serif text-2xl">此货架暂空</div>
                <p class="mono text-sm opacity-50 mt-1">请到 /admin → Products 创建 on_sale 商品</p>
                <a href="/admin" class="inline-flex mt-4 h-9 px-5 rounded-full bg-[var(--ink)] text-white mono text-xs font-semibold">去后台创建 →</a>
            </div>
        @else
            <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-3 gap-4">
                @foreach($products as $p)
                    @include('components.product-card', ['product'=>$p])
                @endforeach
            </div>
            <div class="mt-8 flex justify-center">{{ $products->withQueryString()->links() }}</div>
        @endif
    </section>
</div>
@endsection
