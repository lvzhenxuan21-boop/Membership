@extends('layouts.shop')
@section('title','我的订单')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between">
        <h1 class="serif text-2xl tracking-[-0.02em]">我的订单</h1>
        <span class="mono text-xs px-2.5 py-1 rounded-full bg-white border border-[var(--line)] opacity-60">{{ $orders->total() }} 单</span>
    </div>
    <div class="mt-4 flex gap-2 mono text-xs flex-wrap">
        <a href="{{ route('web.orders') }}" class="px-3 py-1.5 rounded-full border {{ !request('status')?'bg-[var(--ink)] text-white border-[var(--ink)]':'bg-white border-[var(--line)]' }}">全部</a>
        @foreach(['pending'=>'待支付','paid'=>'已支付','shipped'=>'已发货','completed'=>'已完成'] as $k=>$v)
            <a href="{{ route('web.orders').'?status='.$k }}" class="px-3 py-1.5 rounded-full border {{ request('status')===$k?'bg-[var(--ink)] text-white border-[var(--ink)]':'bg-white border-[var(--line)]' }}">{{ $v }}</a>
        @endforeach
    </div>
    <div class="mt-4 space-y-3">
        @forelse($orders as $o)
            <a href="{{ route('web.orders.show',$o->order_no) }}" class="block rounded-[20px] bg-white border border-[var(--line)] p-4 soft hover:shadow-[0_8px_24px_rgba(0,0,0,.06)] transition">
                <div class="flex justify-between mono text-xs"><span class="font-semibold tracking-wide">{{ $o->order_no }}</span><span class="px-2 py-1 rounded-full text-[11px] font-semibold border {{ $o->status==='paid'?'bg-emerald-50 text-emerald-700 border-emerald-200':($o->status==='pending'?'bg-amber-50 text-amber-700 border-amber-200':'bg-[var(--paper2)]') }}">{{ strtoupper($o->status) }}</span></div>
                <div class="mono text-xs opacity-50 mt-1">{{ $o->shop->name }} · {{ $o->created_at->format('Y-m-d H:i') }} · 抽佣 ¥{{ $o->platform_fee }}</div>
                <div class="mono text-xs mt-2 opacity-70 truncate">@foreach($o->items as $it){{ $it->product_name }}×{{ $it->quantity }} · @endforeach</div>
                <div class="mt-1 serif font-bold text-lg">¥{{ number_format($o->pay_amount,2) }} <span class="mono text-xs font-normal opacity-40">/ 原价 ¥{{ $o->total_amount }}</span></div>
            </a>
        @empty
            <div class="rounded-[20px] bg-white border border-dashed border-[var(--line)] p-12 text-center mono text-sm opacity-50">暂无订单</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $orders->withQueryString()->links() }}</div>
</div>
@endsection
