@extends('layouts.shop')
@section('title', $product->name)
@section('content')
<div class="grid lg:grid-cols-[1.1fr_0.9fr] gap-6">
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="aspect-[4/3] bg-[var(--paper2)] relative">
            @if($product->cover)<img src="{{ $product->cover }}" class="w-full h-full object-cover">@else<div class="w-full h-full grid place-items-center mono text-xs opacity-30">NO IMAGE</div>@endif
            <div class="absolute bottom-3 left-3 flex gap-2 mono text-xs">
                <span class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur border border-[var(--line)]">库存 {{ $product->stock }}</span>
                <span class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur border border-[var(--line)]">销量 {{ $product->sales }}</span>
            </div>
        </div>
        <div class="px-4 py-3 flex justify-between mono text-xs opacity-40">
            <span>{{ $product->shop->name }} · #{{ str_pad($product->id,4,'0',STR_PAD_LEFT) }}</span><span>抽佣 {{ rtrim(rtrim($product->shop->platform_fee_rate*100,'0'),'.') }}%</span>
        </div>
    </div>

    <div>
        <div class="rounded-[24px] bg-white border border-[var(--line)] p-6 sm:p-7 soft">
            <div class="mono text-xs tracking-widest opacity-40">{{ $product->shop->name }} · SKU {{ str_pad($product->id,4,'0',STR_PAD_LEFT) }}</div>
            <h1 class="serif text-[26px] sm:text-[30px] leading-[0.95] tracking-[-0.02em] mt-2">{{ $product->name }}</h1>
            <div class="mt-4 flex items-baseline gap-3">
                <span class="serif text-3xl leading-none">¥{{ rtrim(rtrim(number_format($product->price,2),'0'),'.') }}</span>
                @if($product->original_price && $product->original_price>$product->price)<span class="mono text-sm line-through opacity-30">¥{{ rtrim(rtrim(number_format($product->original_price,2),'0'),'.') }}</span>@endif
                <span class="ml-auto mono text-xs px-2.5 py-1 rounded-full {{ $product->stock>0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">{{ $product->stock>0 ? '有货' : '缺货' }}</span>
            </div>
            @if($product->description)<p class="mt-4 text-sm leading-7 text-zinc-600">{{ $product->description }}</p>@endif
            @if($product->specs)<div class="mt-4 mono text-xs bg-[var(--paper2)] border border-[var(--line)] rounded-2xl p-3"><div class="opacity-40 tracking-widest mb-1">SPECS</div><pre class="whitespace-pre-wrap leading-relaxed">{{ json_encode($product->specs, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre></div>@endif

            <div class="mt-6 flex gap-3" x-data="{qty:1}">
                <div class="flex items-center rounded-full border border-[var(--line)] bg-[var(--paper2)] p-1">
                    <button @click="qty=Math.max(1,qty-1)" class="w-8 h-8 rounded-full bg-white border border-[var(--line)] grid place-items-center hover:bg-zinc-50">−</button>
                    <span class="w-10 text-center mono text-sm font-semibold" x-text="qty"></span>
                    <button @click="qty=Math.min({{ $product->stock }},qty+1)" class="w-8 h-8 rounded-full bg-white border border-[var(--line)] grid place-items-center hover:bg-zinc-50">+</button>
                </div>
                <button onclick="addToCart({{ $product->id }}, {{ $product->shop_id }}, @js($product->name), '{{ $product->price }}', @js($product->cover), @js($product->shop->name), parseInt(this.closest('[x-data]').__x.$data.qty||1));"
                        class="flex-1 h-10 rounded-full bg-[var(--accent)] text-white font-semibold hover:bg-[#E63600] soft">加入购物车</button>
                <a href="{{ route('web.cart') }}" class="h-10 px-5 grid place-items-center rounded-full bg-[var(--ink)] text-white text-sm font-medium hover:bg-black">去结算</a>
            </div>
            <div class="mt-3 mono text-[11px] opacity-40 text-center">支持 mock / wallet / 微信 / 支付宝 / Stripe · 单店结算校验</div>
        </div>

        @if($related->count())
        <div class="mt-4 rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
            <div class="mono text-xs tracking-wide px-5 py-3 border-b border-[var(--line)] flex justify-between opacity-60"><span>同店推荐</span><span>{{ $related->count() }} 件</span></div>
            <div class="grid grid-cols-2 divide-x divide-[var(--line)]">
                @foreach($related as $rp)
                    <a href="{{ route('web.products.show',$rp->id) }}" class="flex gap-3 p-3 hover:bg-[var(--paper2)] transition">
                        <div class="w-16 h-16 rounded-xl bg-[var(--paper2)] border border-[var(--line)] overflow-hidden shrink-0">@if($rp->cover)<img src="{{ $rp->cover }}" class="w-full h-full object-cover">@endif</div>
                        <div class="min-w-0"><div class="text-sm font-medium leading-tight line-clamp-2">{{ $rp->name }}</div><div class="serif font-bold mt-1">¥{{ rtrim(rtrim(number_format($rp->price,2),'0'),'.') }}</div></div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
function addToCart(id, shop_id, name, price, cover, shop_name, qty){
    qty = qty||1;
    let cart = [];
    try{ cart = JSON.parse(localStorage.getItem('cart')||'[]') }catch(e){ cart=[] }
    if(cart.length && cart[0].shop_id !== shop_id){
        if(!confirm('购物车已有其他店铺商品，清空后加入？')) return;
        cart=[];
    }
    for(let i=0;i<qty;i++){
        let f=cart.find(x=>x.id===id);
        if(f) f.quantity++; else cart.push({id, shop_id, name, price:parseFloat(price), cover, shop_name, quantity:1});
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    let t=document.createElement('div'); t.textContent='已加入购物车 ×'+qty; t.className='fixed bottom-6 left-1/2 -translate-x-1/2 bg-[var(--ink)] text-white mono text-sm px-4 py-2 rounded-full soft z-50'; document.body.appendChild(t); setTimeout(()=>{t.remove(); location.reload()}, 700);
}
</script>
@endpush
@endsection
