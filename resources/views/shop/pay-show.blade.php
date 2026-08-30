@extends('layouts.shop')
@section('title','支付 '.$payment->order_no)
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="px-5 py-4 flex justify-between items-start border-b border-[var(--line)]">
            <div>
                <div class="mono text-xs tracking-[0.18em] opacity-40">PAYMENT</div>
                <div class="mono font-semibold tracking-wide mt-1">{{ $payment->order_no }}</div>
            </div>
            <span class="mono text-xs font-semibold px-3 py-1 rounded-full border {{ $payment->status==='paid' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-[var(--paper2)]' }}">{{ strtoupper($payment->status) }}</span>
        </div>

        <div class="p-5 mono text-sm grid grid-cols-2 gap-2">
            <div class="opacity-60">内容</div><div class="text-right">{{ $payment->subject }}</div>
            @if($payment->original_amount != $payment->amount)
                <div class="opacity-60">原价</div><div class="text-right line-through opacity-50">¥{{ number_format($payment->original_amount,2) }}</div>
                <div class="opacity-60">优惠</div><div class="text-right">-¥{{ number_format($payment->discount_amount,2) }}</div>
            @endif
            <div class="font-bold border-t border-[var(--line)] pt-2 mt-1">应付</div><div class="text-right font-bold border-t border-[var(--line)] pt-2 mt-1 text-lg">¥{{ number_format($payment->amount,2) }}</div>
        </div>

        @if($sub)
            <div class="px-5 py-3 border-t border-[var(--line)] bg-[var(--paper2)] mono text-xs space-y-1">
                <div>套餐：<span class="font-semibold">{{ $sub->plan?->name ?? '—' }}</span> · 订阅单 <span class="font-semibold">{{ $sub->order_no }}</span></div>
                <div>状态：{{ strtoupper($sub->status) }}@if($sub->ends_at) · 有效期至 {{ $sub->ends_at->format('Y-m-d') }}@endif</div>
            </div>
        @endif

        @if($payment->status==='pending' && $sub)
            <form method="POST" action="{{ route('web.pay.switch', $payment->order_no) }}" class="px-5 py-4 border-t border-[var(--line)]">
                @csrf
                <div class="mono text-xs font-semibold tracking-wide mb-2">支付方式</div>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach($channels as $c)
                        <label class="flex items-center gap-2 rounded-2xl border px-3 py-2 mono text-sm cursor-pointer hover:bg-[var(--paper2)] {{ $c['ch']===$payment->channel ? 'border-[var(--ink)] bg-[var(--paper2)]' : 'border-[var(--line)]' }}">
                            <input type="radio" name="channel" value="{{ $c['ch'] }}" class="accent-[var(--ink)]" {{ $c['ch']===$payment->channel ? 'checked' : '' }} onchange="this.form.submit()">
                            {{ $c['label'] }}@if($c['mock'])<span class="opacity-40 text-xs">（演示）</span>@endif
                        </label>
                    @endforeach
                </div>
            </form>
        @endif

        <div class="p-5 border-t border-[var(--line)]">
            @if($payment->status==='paid')
                <div class="mono text-sm text-emerald-700">✓ 支付完成，权益已生效</div>
                <a href="{{ route('web.home') }}" class="mt-3 h-10 px-5 inline-flex items-center rounded-full bg-[var(--accent)] text-white mono text-sm font-semibold hover:bg-[#E63600]">继续去逛商城 →</a>
            @elseif($payment->status==='pending')
                @if($mockMode)
                    <form method="POST" action="/api/v1/payment/{{ $payment->order_no }}/mock-pay" onsubmit="event.preventDefault(); this.disabled=true; fetch(this.action,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(r=>r.json()).then(j=>{ if(j.ok){ location.reload(); } else { alert(j.message||'支付失败'); this.disabled=false; } })">
                        @csrf<button class="h-10 px-5 rounded-full bg-[var(--accent)] text-white mono text-sm font-semibold hover:bg-[#E63600]">立即支付 ¥{{ number_format($payment->amount,2) }}</button>
                    </form>
                @elseif($payment->pay_url)
                    <a href="{{ $payment->pay_url }}" class="h-10 px-5 inline-flex items-center rounded-full bg-[var(--accent)] text-white mono text-sm font-semibold hover:bg-[#E63600]">前往收银台支付</a>
                @else
                    <div class="mono text-sm opacity-60">请完成支付，结果回调后此页自动更新</div>
                @endif
            @elseif($payment->status==='cancelled')
                <div class="mono text-sm opacity-60">支付单已取消，<a class="underline" href="{{ route('web.pricing') }}">重新下单</a></div>
            @else
                <div class="mono text-sm opacity-60">当前状态：{{ strtoupper($payment->status) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
