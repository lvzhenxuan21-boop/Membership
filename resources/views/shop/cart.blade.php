@extends('layouts.shop')
@section('title','购物车')
@section('content')
<div class="max-w-3xl mx-auto" x-data="cartPage()" x-init="load()">
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="px-6 py-4 flex items-center justify-between border-b border-[var(--line)]">
            <span class="serif text-xl">购物车</span><span class="mono text-xs opacity-50">localStorage · 单店校验</span>
        </div>
        <template x-if="items.length===0">
            <div class="p-12 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-[var(--paper2)] border border-[var(--line)] grid place-items-center text-xl">∅</div>
                <div class="serif text-xl mt-4">购物车是空的</div>
                <p class="mono text-xs opacity-50 mt-1">去挑几件放进来</p>
                <a href="{{ url('/') }}" class="inline-flex mt-4 h-9 px-5 rounded-full bg-[var(--ink)] text-white mono text-xs font-semibold">去逛逛 →</a>
            </div>
        </template>
        <template x-if="items.length>0">
            <div>
                <div class="divide-y divide-[var(--line)]">
                    <template x-for="(it,idx) in items" :key="it.id">
                        <div class="p-4 flex gap-4 items-center">
                            <img :src="it.cover||''" class="w-16 h-16 rounded-xl border border-[var(--line)] bg-[var(--paper2)] object-cover">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm truncate" x-text="it.name"></div>
                                <div class="mono text-xs opacity-50" x-text="it.shop_name"></div>
                                <div class="serif font-bold" x-text="'¥'+Number(it.price).toFixed(2)"></div>
                            </div>
                            <div class="flex items-center gap-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] p-1">
                                <button @click="dec(idx)" class="w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center">−</button>
                                <span class="w-7 text-center mono text-sm font-semibold" x-text="it.quantity"></span>
                                <button @click="inc(idx)" class="w-7 h-7 rounded-full bg-white border border-[var(--line)] grid place-items-center">+</button>
                            </div>
                            <button @click="rm(idx)" class="mono text-xs opacity-40 hover:opacity-100 underline">移除</button>
                        </div>
                    </template>
                </div>
                <div class="p-4 bg-[var(--paper2)] flex flex-wrap gap-3 items-center justify-between">
                    <div class="mono text-sm">合计 <span class="serif text-xl font-bold" x-text="'¥'+total.toFixed(2)"></span> · <span x-text="count"></span> 件</div>
                    <div class="flex gap-2">
                        <button @click="clear()" class="h-9 px-4 rounded-full border border-[var(--line)] bg-white mono text-xs font-semibold">清空</button>
                        <a href="{{ route('web.checkout') }}" class="h-9 px-6 rounded-full bg-[var(--accent)] text-white mono text-xs font-semibold grid place-items-center hover:bg-[#E63600]">去结算 →</a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
@push('scripts')
<script>
function cartPage(){
    return {
        items:[],
        get total(){ return this.items.reduce((s,i)=>s+i.price*i.quantity,0)},
        get count(){ return this.items.reduce((s,i)=>s+i.quantity,0)},
        load(){ try{ this.items=JSON.parse(localStorage.getItem('cart')||'[]')}catch(e){this.items=[]} },
        save(){ localStorage.setItem('cart', JSON.stringify(this.items)) },
        inc(i){ this.items[i].quantity++; this.save() },
        dec(i){ if(this.items[i].quantity>1) this.items[i].quantity--; this.save() },
        rm(i){ this.items.splice(i,1); this.save() },
        clear(){ this.items=[]; this.save() }
    }
}
</script>
@endpush
@endsection
