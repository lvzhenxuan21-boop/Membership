@extends('layouts.shop')
@section('title','会员套餐')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="text-center max-w-2xl mx-auto">
        <div class="mono text-xs tracking-[0.18em] opacity-40">PRICING</div>
        <h1 class="serif text-3xl sm:text-4xl tracking-[-0.03em] mt-2">为每一家店选配会员</h1>
        <p class="mono text-sm opacity-50 mt-2">MembershipPlan 与商城同仓 · 会员权益随店隔离</p>
    </div>
    <div class="mt-8 grid md:grid-cols-3 gap-4">
        @foreach($plans as $plan)
            <div class="rounded-[24px] border p-6 flex flex-col soft {{ $plan->is_recommended?'bg-[var(--ink)] text-white border-[var(--ink)]':'bg-white border-[var(--line)]' }}">
                @if($plan->is_recommended)<div class="mono text-xs px-2.5 py-1 rounded-full bg-[var(--accent)] text-white self-start">★ 最受欢迎</div>@endif
                <div class="font-semibold mt-3">{{ $plan->name }}</div>
                <div class="mt-2 flex items-baseline gap-2"><span class="serif text-3xl leading-none">¥{{ rtrim(rtrim(number_format($plan->price,2),'0'),'.') }}</span><span class="mono text-xs opacity-50">/ {{ $plan->billing_cycle }}</span></div>
                @if($plan->original_price)<div class="mono text-xs line-through opacity-30">原价 ¥{{ $plan->original_price }}</div>@endif
                <ul class="mt-4 mono text-xs leading-6 flex-1 border-t border-dashed {{ $plan->is_recommended?'border-white/20':'border-[var(--line)]' }} pt-3 space-y-1 opacity-80">
                    @foreach($plan->benefits ?? [] as $b)<li>· {{ $b }}</li>@endforeach
                    @foreach($plan->features as $f)<li>· {{ $f->name }} @if($f->pivot->quota) <span class="opacity-50">×{{ $f->pivot->quota }}</span>@endif</li>@endforeach
                </ul>
                <form method="POST" action="{{ route('web.pricing.subscribe') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="w-full h-10 rounded-full font-semibold text-sm {{ $plan->is_recommended?'bg-white text-[var(--ink)] hover:bg-zinc-100':'bg-[var(--ink)] text-white hover:bg-black' }}">立即订阅</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
