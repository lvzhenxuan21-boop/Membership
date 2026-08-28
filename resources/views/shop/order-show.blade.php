@extends('layouts.shop')
@section('title','订单 '.$order->order_no)
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('web.orders') }}" class="mono text-xs underline">← 返回订单列表</a>
    <div class="mt-3 rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="px-5 py-4 flex justify-between items-start border-b border-[var(--line)]">
            <div><div class="mono font-semibold tracking-wide">{{ $order->order_no }}</div><div class="mono text-xs opacity-50">{{ $order->shop->name }} · {{ $order->created_at->format('Y-m-d H:i:s') }}</div></div>
            <span class="mono text-xs font-semibold px-3 py-1 rounded-full border bg-[var(--paper2)]">{{ strtoupper($order->status) }}</span>
        </div>
        <div class="divide-y divide-[var(--line)]">
            @foreach($order->items as $it)
                <div class="p-3 flex justify-between mono text-sm"><span>{{ $it->product_name }} × {{ $it->quantity }} <span class="opacity-40">@ ¥{{ $it->price }}</span></span><span class="font-semibold">¥{{ number_format($it->amount,2) }}</span></div>
            @endforeach
        </div>
        <div class="p-4 mono text-sm grid grid-cols-2 gap-2 bg-[var(--paper2)]">
            <div class="opacity-60">总计</div><div class="text-right">¥{{ number_format($order->total_amount,2) }}</div>
            <div class="opacity-60">优惠</div><div class="text-right">-¥{{ number_format($order->discount_amount,2) }}</div>
            <div class="opacity-60">平台抽佣</div><div class="text-right">¥{{ $order->platform_fee }}</div>
            <div class="font-bold border-t border-[var(--line)] pt-2 mt-1">实付</div><div class="text-right font-bold border-t border-[var(--line)] pt-2 mt-1">¥{{ number_format($order->pay_amount,2) }}</div>
        </div>
        @if($order->address)<div class="m-3 mono text-xs bg-white border border-[var(--line)] rounded-2xl p-3"><pre class="whitespace-pre-wrap">{{ json_encode($order->address, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre></div>@endif
    </div>
    <div class="mt-4 rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
        <div class="mono text-xs font-semibold tracking-wide">支付单</div>
        @if($payment)
            <div class="mt-2 mono text-sm space-y-1">
                <div>支付单号：<span class="font-semibold">{{ $payment->order_no }}</span> · {{ $payment->channel }} · <span class="px-2 py-1 rounded-full bg-[var(--paper2)] border text-xs">{{ $payment->status }}</span></div>
                <div>金额：¥{{ $payment->amount }}</div>
                @if($payment->status==='pending')
                    <form method="POST" action="/api/v1/payment/{{ $payment->order_no }}/mock-pay" onsubmit="event.preventDefault(); fetch(this.action,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(j=>{alert(JSON.stringify(j)); location.reload()})">
                        @csrf<button class="mt-2 h-9 px-4 rounded-full bg-[var(--accent)] text-white mono text-xs font-semibold hover:bg-[#E63600]">模拟一键支付</button>
                    </form>
                @endif
            </div>
        @else
            <div class="mono text-sm opacity-50">暂无支付单</div>
        @endif
    </div>
</div>
@endsection
