@extends('layouts.shop')
@section('title','个人中心')
@section('content')
<div class="max-w-[1280px] mx-auto">
    {{-- Header card --}}
    <div class="rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
        <div class="h-24 bg-gradient-to-br from-[#FFF1E6] via-[#FFE4D6] to-[#F0E8DD] relative">
            <div class="absolute inset-0 opacity-10" style="background:radial-gradient(circle at 70% 30%, var(--accent) 0%, transparent 60%)"></div>
            <div class="absolute -bottom-8 left-6 flex items-end gap-4">
                <div class="w-20 h-20 rounded-[20px] bg-[var(--ink)] text-white grid place-items-center serif text-2xl border-4 border-white soft">{{ mb_substr($user->name,0,1) }}</div>
                <div class="pb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="serif text-xl leading-none">{{ $user->name }}</span>
                        <span class="mono text-[11px] px-2 py-1 rounded-full bg-[var(--ink)] text-white">{{ $profile->level?->name ?? '普通会员' }}</span>
                        @if($profile->level?->benefits)<span class="mono text-[11px] px-2 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] opacity-70">{{ is_array($profile->level->benefits) ? implode(' · ', array_slice($profile->level->benefits,0,2)) : '' }}</span>@endif
                    </div>
                    <div class="mono text-xs opacity-60 mt-1">{{ $user->email }} · {{ $profile->member_no }} · {{ $profile->branch?->name ?? '总店' }} · {{ $profile->joined_at?->format('Y-m-d') }}</div>
                </div>
            </div>
            <div class="absolute top-4 right-4 flex gap-2">
                <a href="{{ route('web.orders') }}" class="h-8 px-3 inline-flex items-center rounded-full bg-white/90 backdrop-blur border border-[var(--line)] mono text-xs font-medium">订单</a>
                <a href="{{ route('web.pricing') }}" class="h-8 px-4 inline-flex items-center rounded-full bg-[var(--ink)] text-white mono text-xs font-semibold">升级套餐</a>
            </div>
        </div>
        <div class="pt-12 px-6 pb-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">积分</div>
                    <div class="serif text-2xl leading-none mt-1">{{ $profile->points }}</div>
                    <div class="mono text-xs opacity-50">成长值 {{ $profile->growth }}</div>
                </div>
                <div class="rounded-2xl bg-[var(--ink)] text-white p-3">
                    <div class="mono text-[10px] tracking-widest opacity-60">余额</div>
                    <div class="serif text-2xl leading-none mt-1">¥{{ number_format($wallet?->balance ?? 0,2) }}</div>
                    <div class="mono text-xs opacity-60">累计充值 ¥{{ number_format($wallet?->total_recharged ?? 0,2) }}</div>
                </div>
                <div class="rounded-2xl bg-white border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">消费</div>
                    <div class="serif text-2xl leading-none mt-1">¥{{ number_format($profile->total_spent,2) }}</div>
                    <div class="mono text-xs opacity-50">{{ $profile->total_orders }} 单</div>
                </div>
                <div class="rounded-2xl bg-white border border-[var(--line)] p-3">
                    <div class="mono text-[10px] tracking-widest opacity-50">会员有效期</div>
                    @if($subs->first()?->ends_at)
                        <div class="serif text-sm leading-tight mt-1">{{ $subs->first()->ends_at->format('Y-m-d') }}</div>
                        <div class="mono text-xs opacity-50">{{ $subs->first()->plan->name }} · {{ $subs->first()->status }}</div>
                    @else
                        <div class="serif text-sm mt-1">{{ $subs->first()?->plan->name ?? '未订阅' }}</div>
                        <div class="mono text-xs opacity-50">{{ $subs->first()?->status ?? '去选套餐' }}</div>
                    @endif
                </div>
            </div>
            @if($nextLevel)
                <div class="mt-4 rounded-2xl bg-[var(--paper2)] border border-[var(--line)] p-3 flex items-center gap-3">
                    <div class="flex-1">
                        <div class="mono text-xs">距 <span class="font-semibold">{{ $nextLevel->name }}</span> 还需 {{ max(0,$nextLevel->min_growth - $profile->growth) }} 成长值</div>
                        <div class="mt-2 h-2 rounded-full bg-white border border-[var(--line)] overflow-hidden"><div class="h-full bg-[var(--accent)] rounded-full" style="width: {{ $progress }}%"></div></div>
                    </div>
                    <span class="mono text-xs font-semibold">{{ $progress }}%</span>
                </div>
            @endif
            {{-- 签到快捷 --}}
            <div class="mt-4 rounded-2xl bg-[var(--ink)] text-white p-4 flex items-center justify-between soft">
                <div>
                    <div class="mono text-[11px] tracking-widest opacity-60">DAILY CHECK-IN</div>
                    <div class="font-semibold text-sm mt-1">已连签 {{ $streak }} 天 · {{ $todayDone ? '今日已签' : '今日未签' }}</div>
                    <div class="mono text-xs opacity-60">基础10分 · 连签加成 3/7/15/30天</div>
                </div>
                @if($todayDone)
                    <span class="h-9 px-5 inline-flex items-center rounded-full bg-white/10 border border-white/20 mono text-xs font-semibold">已签 ✓</span>
                @else
                    <form method="POST" action="{{ route('web.checkin.store') }}">@csrf<button class="h-9 px-5 inline-flex items-center rounded-full bg-[var(--accent)] text-white mono text-xs font-semibold hover:bg-[#E63600]">去签到 +10</button></form>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid lg:grid-cols-[1.1fr_0.9fr] gap-6">
        {{-- Left: levels + subs + edit --}}
        <div class="space-y-6">
            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <div class="flex items-center justify-between">
                    <h2 class="serif text-lg">等级体系</h2>
                    <span class="mono text-xs px-2 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] opacity-60">{{ $levels->count() }} 档</span>
                </div>
                <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($levels as $lv)
                        <div class="rounded-2xl border p-3 text-center {{ $profile->membership_level_id===$lv->id ? 'bg-[var(--ink)] text-white border-[var(--ink)]' : 'bg-[var(--paper2)] border-[var(--line)]' }}">
                            <div class="mono text-[10px] tracking-widest opacity-60">LV{{ $lv->level }}</div>
                            <div class="font-semibold text-sm mt-1">{{ $lv->name }}</div>
                            <div class="mono text-xs opacity-60 mt-1">≥{{ $lv->min_growth }} 成长值</div>
                            @if($profile->membership_level_id===$lv->id)<div class="mt-2 mono text-[10px] px-2 py-1 rounded-full bg-white text-[var(--ink)] inline-block">当前</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <div class="flex items-center justify-between">
                    <h2 class="serif text-lg">我的订阅</h2>
                    <a href="{{ route('web.pricing') }}" class="mono text-xs px-3 py-1 rounded-full border border-[var(--line)] hover:bg-[var(--paper2)]">去选购</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($subs as $s)
                        <div class="rounded-2xl border border-[var(--line)] p-3 flex justify-between items-center {{ $s->status==='active' ? 'bg-white' : 'bg-[var(--paper2)] opacity-70' }}">
                            <div>
                                <div class="font-medium text-sm">{{ $s->plan->name }} <span class="mono text-xs opacity-50">/ {{ $s->plan->billing_cycle }}</span></div>
                                <div class="mono text-xs opacity-60">{{ $s->order_no }} · {{ $s->starts_at?->format('Y-m-d') }} → {{ $s->ends_at?->format('Y-m-d') ?? '永久' }}</div>
                            </div>
                            <span class="mono text-xs px-2 py-1 rounded-full border {{ $s->status==='active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-white' }}">{{ strtoupper($s->status) }}</span>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[var(--line)] p-8 text-center mono text-sm opacity-50">暂无订阅，去挑个套餐</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <h2 class="serif text-lg">资料设置</h2>
                <p class="mono text-xs opacity-60 mt-1">更新后实时生效，会员档案与钱包同店隔离</p>
                <form method="POST" action="{{ route('web.me.update') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="mono text-xs">昵称<input name="name" value="{{ old('name',$user->name) }}" class="mt-1 w-full h-9 px-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] text-sm focus:outline-none"></label>
                        <label class="mono text-xs">真实姓名<input name="real_name" value="{{ old('real_name',$profile->real_name) }}" class="mt-1 w-full h-9 px-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] text-sm focus:outline-none"></label>
                        <label class="mono text-xs">手机<input name="phone" value="{{ old('phone',$profile->phone) }}" class="mt-1 w-full h-9 px-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] text-sm focus:outline-none"></label>
                        <label class="mono text-xs">性别<select name="gender" class="mt-1 w-full h-9 px-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] text-sm focus:outline-none"><option value="unknown" @selected($profile->gender==='unknown')>保密</option><option value="male" @selected($profile->gender==='male')>男</option><option value="female" @selected($profile->gender==='female')>女</option></select></label>
                        <label class="mono text-xs sm:col-span-2">生日<input type="date" name="birthday" value="{{ old('birthday', $profile->birthday?->format('Y-m-d')) }}" class="mt-1 w-full h-9 px-3 rounded-full bg-[var(--paper2)] border border-transparent focus:bg-white focus:border-[var(--line)] text-sm focus:outline-none"></label>
                    </div>
                    <button class="w-full h-9 rounded-full bg-[var(--ink)] text-white mono text-sm font-semibold hover:bg-black">保存资料</button>
                </form>
            </div>
        </div>

        {{-- Right: orders + wallet + points --}}
        <div class="space-y-6">
            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <div class="flex items-center justify-between">
                    <h2 class="serif text-lg">最近订单</h2>
                    <a href="{{ route('web.orders') }}" class="mono text-xs px-3 py-1 rounded-full bg-[var(--ink)] text-white">全部</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($orders as $o)
                        <a href="{{ route('web.orders.show',$o->order_no) }}" class="block rounded-2xl border border-[var(--line)] p-3 hover:bg-[var(--paper2)] transition">
                            <div class="flex justify-between mono text-xs"><span class="font-semibold">{{ $o->order_no }}</span><span class="px-2 py-1 rounded-full border text-[11px] {{ $o->status==='paid'?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-white' }}">{{ $o->status }}</span></div>
                            <div class="mono text-xs opacity-60 mt-1 truncate">@foreach($o->items as $it){{ $it->product_name }}×{{ $it->quantity }} · @endforeach</div>
                            <div class="serif font-bold">¥{{ number_format($o->pay_amount,2) }}</div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[var(--line)] p-6 text-center mono text-xs opacity-50">暂无订单</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <h2 class="serif text-lg">钱包流水</h2>
                <div class="mono text-xs opacity-50">当前余额 ¥{{ number_format($wallet?->balance ?? 0,2) }}</div>
                <div class="mt-3 divide-y divide-[var(--line)] border border-[var(--line)] rounded-2xl overflow-hidden">
                    @forelse($walletTx as $tx)
                        <div class="p-3 flex justify-between mono text-xs bg-white">
                            <div><span class="font-semibold">{{ $tx->type }}</span> <span class="opacity-60">{{ $tx->description }}</span><div class="opacity-40">{{ $tx->created_at->format('m-d H:i') }} · 余额 ¥{{ $tx->balance_after }}</div></div>
                            <span class="font-semibold {{ $tx->amount >=0 ? 'text-emerald-600' : 'text-[var(--accent)]' }}">{{ $tx->amount >=0 ? '+' : '' }}{{ $tx->amount }}</span>
                        </div>
                    @empty
                        <div class="p-6 text-center mono text-xs opacity-50 bg-[var(--paper2)]">暂无流水</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[24px] bg-white border border-[var(--line)] p-5 soft">
                <h2 class="serif text-lg">积分明细</h2>
                <div class="mono text-xs opacity-50">{{ $profile->points }} 分 · 成长值 {{ $profile->growth }}</div>
                <div class="mt-3 divide-y divide-[var(--line)] border border-[var(--line)] rounded-2xl overflow-hidden">
                    @forelse($ledgers as $lg)
                        <div class="p-3 flex justify-between mono text-xs bg-white">
                            <div><span class="font-semibold">{{ $lg->type }}</span> <span class="opacity-60">{{ $lg->description }}</span><div class="opacity-40">{{ $lg->created_at->format('m-d H:i') }}</div></div>
                            <span class="font-semibold {{ $lg->points >=0 ? 'text-emerald-600' : 'text-[var(--accent)]' }}">{{ $lg->points >=0 ? '+' : '' }}{{ $lg->points }}</span>
                        </div>
                    @empty
                        <div class="p-6 text-center mono text-xs opacity-50 bg-[var(--paper2)]">暂无记录</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
