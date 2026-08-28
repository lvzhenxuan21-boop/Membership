@extends('layouts.shop')
@section('title','结算')
@section('content')
<div class="max-w-3xl mx-auto" x-data="checkoutPage()" x-init="load()">
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="px-6 py-4 border-b border-[var(--line)] flex justify-between items-center">
            <span class="serif text-xl">结算</span><span class="mono text-xs opacity-40">business_type=order</span>
        </div>
        @guest
            <div class="mx-6 mt-4 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 mono text-sm flex justify-between items-center">
                <span>请先 <a href="{{ route('web.login') }}" class="underline font-bold">登录</a> 后下单</span><a href="{{ route('web.login') }}" class="h-7 px-3 rounded-full bg-[var(--ink)] text-white mono text-xs grid place-items-center">去登录</a>
            </div>
        @endguest
        <div class="p-6">
            <div x-show="items.length===0" class="mono text-sm opacity-50">购物车为空，<a href="{{ url('/') }}" class="underline">去选购</a></div>
            <div x-show="items.length>0">
                <div class="mono text-xs opacity-50" x-text="items[0]?.shop_name"></div>
                <div class="mt-2 divide-y border border-[var(--line)] rounded-2xl overflow-hidden">
                    <template x-for="it in items" :key="it.id">
                        <div class="p-3 flex justify-between mono text-sm"><span x-text="it.name+' × '+it.quantity"></span><span class="font-semibold" x-text="'¥'+(it.price*it.quantity).toFixed(2)"></span></div>
                    </template>
                </div>
                <div class="mt-3 flex justify-between serif text-xl">合计 <span x-text="'¥'+total.toFixed(2)"></span></div>
            </div>
        </div>
        <form method="POST" action="{{ route('web.checkout.place') }}" class="px-6 pb-6 space-y-3" @submit="onSubmit($event)">
            @csrf
            <input type="hidden" name="shop_id" :value="shopId">
            <template x-for="(it,idx) in items" :key="it.id"><span><input type="hidden" :name="'items['+idx+'][product_id]'" :value="it.id"><input type="hidden" :name="'items['+idx+'][quantity]'" :value="it.quantity"></span></template>
            <label class="block mono text-xs tracking-wide">支付方式
                <select name="channel" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] bg-[var(--paper2)] mono text-sm focus:outline-none focus:bg-white">
                    <option value="mock">模拟支付（演示）</option><option value="wallet">钱包余额</option><option value="wechat">微信</option><option value="alipay">支付宝</option><option value="stripe">Stripe</option>
                </select>
            </label>
            <div class="mono text-xs">收货信息
                <input name="address[contact]" placeholder="收货人" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] bg-white mono text-sm">
                <input name="address[phone]" placeholder="手机号" class="mt-2 w-full h-10 px-3 rounded-full border border-[var(--line)] bg-white mono text-sm">
                <input name="address[detail]" placeholder="详细地址" class="mt-2 w-full h-10 px-3 rounded-full border border-[var(--line)] bg-white mono text-sm">
            </div>
            <button type="submit" class="w-full h-11 rounded-full bg-[var(--ink)] text-white font-semibold hover:bg-black" :disabled="items.length===0">提交订单</button>
            <div class="mono text-xs opacity-40 text-center">提交后创建 Order + Payment，mock 自动标记 paid</div>
        </form>
    </div>
</div>
@push('scripts')
<script>
function checkoutPage(){
    return {
        items:[], shopId:'',
        get total(){ return this.items.reduce((s,i)=>s+i.price*i.quantity,0)},
        load(){ try{ this.items=JSON.parse(localStorage.getItem('cart')||'[]')}catch(e){this.items=[]}; if(this.items.length) this.shopId=this.items[0].shop_id },
        onSubmit(e){ if(this.items.length===0){ e.preventDefault(); alert('购物车为空'); return; } setTimeout(()=>{ if(document.querySelector('.bg-emerald-50')) localStorage.removeItem('cart') }, 500); }
    }
}
</script>
@endpush
@endsection
