@extends('layouts.shop')
@section('title','每日签到')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="px-6 py-6 bg-gradient-to-br from-[#FFF1E6] via-[#FFE4D6] to-[#F0E8DD] relative">
            <div class="mono text-[11px] tracking-[0.16em] opacity-50">DAILY CHECK-IN</div>
            <h1 class="serif text-2xl mt-1">每日签到</h1>
            <p class="mono text-xs opacity-60 mt-1">连续签到领成长值，连 3/7/15/30 天额外加成</p>
            <div class="mt-4 flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-[var(--ink)] text-white grid place-items-center serif text-xl">{{ $profile?->points ?? 0 }}</div>
                <div>
                    <div class="mono text-xs opacity-60">当前积分</div>
                    <div class="serif text-lg leading-none">成长值 {{ $profile?->growth ?? 0 }} · {{ $profile?->level?->name ?? '普通会员' }}</div>
                </div>
                <div class="ml-auto text-right">
                    <div class="mono text-xs opacity-60">已连签</div>
                    <div class="serif text-2xl leading-none">{{ $streak }}<span class="mono text-xs"> 天</span></div>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-7 gap-2">
                @foreach($calendar as $c)
                    <div class="rounded-2xl border p-2 text-center {{ $c['done'] ? 'bg-[var(--ink)] text-white border-[var(--ink)]' : 'bg-[var(--paper2)] border-[var(--line)]' }} {{ $c['isToday'] ? 'ring-2 ring-[var(--accent)] ring-offset-1' : '' }}">
                        <div class="mono text-[10px] opacity-60">{{ $c['week'] }}</div>
                        <div class="mono text-xs font-semibold">{{ $c['date'] }}</div>
                        <div class="mt-1 w-6 h-6 mx-auto rounded-full grid place-items-center text-xs {{ $c['done'] ? 'bg-white text-[var(--ink)]' : 'bg-white border border-[var(--line)] opacity-40' }}">{{ $c['done'] ? '✓' : '·' }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 mono text-xs opacity-60 text-center">基础 10 分 · 3天+5 · 7天+20 · 15天+50 · 30天+100</div>
            @if($todayDone)
                <div class="mt-4 h-11 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 grid place-items-center mono text-sm font-semibold">今日已签到 ✓ 明天再来</div>
            @else
                <form method="POST" action="{{ route('web.checkin.store') }}" class="mt-4">
                    @csrf
                    <button class="w-full h-11 rounded-full bg-[var(--accent)] text-white mono text-sm font-semibold hover:bg-[#E63600] soft">立即签到 +10 积分 →</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
        <h2 class="serif text-lg">签到记录</h2>
        <div class="mt-3 divide-y divide-[var(--line)] border border-[var(--line)] rounded-2xl overflow-hidden">
            @forelse($history as $h)
                <div class="p-3 flex justify-between mono text-xs bg-white">
                    <span>{{ $h->checked_in_at->format('Y-m-d H:i') }} · {{ $h->method }}</span>
                    <span class="opacity-60">{{ $h->checked_in_at->isToday() ? '今日' : $h->checked_in_at->diffForHumans() }}</span>
                </div>
            @empty
                <div class="p-6 text-center mono text-xs opacity-50 bg-[var(--paper2)]">还没有签到记录</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('web.me') }}" class="mono text-xs px-4 py-2 rounded-full bg-white border border-[var(--line)] hover:bg-[var(--paper2)]">← 返回个人中心</a>
    </div>
</div>
@endsection
