<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    private function tenant(Request $r)
    {
        return $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r);
    }

    // 首页：有 tenant → 商城，无 tenant → SaaS 落地页
    public function index(Request $r)
    {
        $tenant = $this->tenant($r);
        // path 兼容 /shop/{slug}
        if (!$tenant && $r->is('shop/*')) {
            $slug = explode('/', trim($r->path(), '/'))[1] ?? null;
            if ($slug) $tenant = \App\Models\Tenant::where('slug', $slug)->first();
        }

        if (!$tenant) {
            // 裸域获客首页
            $stores = \App\Models\Tenant::where('status', 'active')->orderByDesc('id')->take(8)->get();
            $storeCount = \App\Models\Tenant::where('status', 'active')->count();
            $demoStore = \App\Models\Tenant::where('slug', 'demo')->where('status', 'active')->first() ?? $stores->first();
            // Hero 预览卡展示在售商品实图
            $samples = \App\Models\Product::with('shop')->where('status', 'on_sale')->whereNotNull('cover')->orderByDesc('sales')->take(3)->get();
            return view('landing', compact('stores', 'storeCount', 'demoStore', 'samples'));
        }

        $shops = Shop::where('tenant_id', $tenant->id)->where('status', 'active')->get();
        $q = Product::with('shop')->where('tenant_id', $tenant->id)->where('status', 'on_sale');
        if ($r->filled('shop_id')) $q->where('shop_id', $r->shop_id);
        if ($r->filled('keyword')) $q->where('name', 'like', '%'.$r->keyword.'%');
        $products = $q->orderByDesc('id')->paginate(12)->withQueryString();

        return view('shop.index', compact('tenant', 'shops', 'products'));
    }

    public function show(Request $r, int $id)
    {
        $tenant = $this->tenant($r);
        $product = Product::with('shop')->findOrFail($id);
        if ($tenant && $product->tenant_id !== $tenant->id) abort(404);
        $related = Product::where('shop_id', $product->shop_id)->where('id', '!=', $product->id)->where('status', 'on_sale')->limit(4)->get();
        return view('shop.show', compact('product', 'tenant', 'related'));
    }

    public function cart(Request $r)
    {
        $tenant = $this->tenant($r);
        return view('shop.cart', compact('tenant'));
    }

    public function checkout(Request $r)
    {
        $tenant = $this->tenant($r);
        $tid = $tenant?->id ?? (\App\Models\Tenant::where('slug','demo')->value('id') ?? 1);
        $user = Auth::user();
        $points = 0;
        if ($user) {
            $points = (int) (\App\Models\MemberProfile::where('tenant_id',$tid)->where('user_id',$user->id)->value('points') ?? 0);
        }
        $pointsPerYuan = max(1, (int) config('membership.points_per_yuan', 100));
        $pointsEnabled = (bool) config('membership.points_redeem_enabled', true);
        return view('shop.checkout', compact('tenant','points','pointsPerYuan','pointsEnabled'));
    }

    public function placeOrder(Request $r, OrderService $orderSvc)
    {
        $data = $r->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'channel' => 'nullable|string|in:mock,wallet,wechat,alipay,stripe,manual',
            'address' => 'nullable|array',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'use_points' => 'nullable|boolean',
        ]);
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error', '请先登录');

        try {
            $order = $orderSvc->placeOrder(
                $user, $tenant, $data['shop_id'], $data['items'],
                $data['channel'] ?? null, $data['coupon_id'] ?? null, $data['address'] ?? null,
                (bool)($data['use_points'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('web.orders.show', $order->order_no)->with('success', '下单成功，订单号 '.$order->order_no);
    }

    public function orders(Request $r)
    {
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login');
        $q = Order::with(['shop','items'])->where('user_id', $user->id)->orderByDesc('id');
        if ($tenant) $q->where('tenant_id', $tenant->id);
        if ($r->filled('status')) $q->where('status', $r->status);
        $orders = $q->paginate(10)->withQueryString();
        return view('shop.orders', compact('orders','tenant'));
    }

    public function orderShow(Request $r, string $orderNo)
    {
        $tenant = $this->tenant($r);
        $order = Order::with(['shop','items.product'])->where('order_no', $orderNo)->where('user_id', Auth::id())->firstOrFail();
        $payment = \App\Models\Payment::where('order_no', $order->payment_order_no)->first();
        return view('shop.order-show', compact('order','payment','tenant'));
    }

    public function pricing(Request $r)
    {
        $tenant = $this->tenant($r);
        $tid = $tenant? $tenant->id : 1;
        $plans = MembershipPlan::with('features')->where('tenant_id', $tid)->where('is_active', true)->orderBy('price')->get();
        return view('shop.pricing', compact('plans','tenant'));
    }

    public function subscribePlan(Request $r, PaymentService $paySvc)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录再订阅');
        $data = $r->validate(['plan_id'=>'required|integer|exists:membership_plans,id']);
        $tenant = $this->tenant($r);
        $tid = $tenant? $tenant->id : 1;
        $plan = MembershipPlan::where('tenant_id',$tid)->findOrFail($data['plan_id']);
        try {
            // 走支付单（PaymentService 内部强制按套餐价计费），支付成功后自动激活订阅
            $payment = $paySvc->create($tid, $user->id, 'subscription', (float)$plan->price, 'mock', ['plan_id'=>$plan->id]);
        } catch (\Throwable $e) {
            return back()->withErrors(['plan_id'=>$e->getMessage()]);
        }
        return redirect()->route('web.pay.show', ['orderNo'=>$payment->order_no])->with('success','订单已创建，请完成支付');
    }

    // 通用支付页（订阅等业务类型；订单支付在 order-show 内）
    public function payShow(Request $r, string $orderNo)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录');
        $payment = \App\Models\Payment::where('order_no',$orderNo)->firstOrFail();
        if ($payment->user_id !== $user->id) abort(403);
        $sub = ($payment->business_type==='subscription' && $payment->business_id)
            ? \App\Models\Subscription::with('plan')->find($payment->business_id) : null;
        $mockMode = \App\Services\Payments\PaymentGatewayFactory::make($payment->channel)->isMockMode();
        // 页面可选渠道：标记每个渠道当前是否为演示模式（未配置密钥降级）
        $labels = ['mock'=>'演示支付','wallet'=>'余额支付','wechat'=>'微信支付','alipay'=>'支付宝','stripe'=>'Stripe 境外卡'];
        $channels = collect($labels)->map(fn($label,$ch)=>[
            'ch'=>$ch, 'label'=>$label,
            'mock'=>\App\Services\Payments\PaymentGatewayFactory::make($ch)->isMockMode(),
        ])->values();
        return view('shop.pay-show', compact('payment','sub','mockMode','channels'));
    }

    // 切换支付渠道：取消旧支付单（连带 pending 订阅），按新渠道重开
    public function switchChannel(Request $r, string $orderNo, PaymentService $paySvc)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login');
        $payment = \App\Models\Payment::where('order_no',$orderNo)->firstOrFail();
        if ($payment->user_id !== $user->id) abort(403);
        $data = $r->validate(['channel'=>'required|in:mock,wallet,wechat,alipay,stripe']);
        $to = $data['channel'];
        if ($to === $payment->channel || !$payment->isPending()) return redirect()->route('web.pay.show', $orderNo);
        if ($payment->business_type !== 'subscription') {
            return redirect()->route('web.pay.show', $orderNo)->with('error','该业务类型暂不支持切换渠道');
        }
        $sub = \App\Models\Subscription::find($payment->business_id);
        if (!$sub || $sub->status !== 'pending') {
            return redirect()->route('web.pay.show', $orderNo)->with('error','订阅单状态已变化，无法切换渠道');
        }
        // 余额渠道预检：避免生成一笔永远付不掉的钱包支付单
        if ($to === 'wallet') {
            $w = \App\Models\Wallet::where('tenant_id',$payment->tenant_id)->where('user_id',$user->id)->first();
            if (!$w || bccomp((string)$w->balance, (string)$payment->amount, 2) < 0) {
                return back()->withErrors(['channel'=>'余额不足，请先充值或换其他支付渠道']);
            }
        }
        $paySvc->cancel($payment);
        $new = $paySvc->create($payment->tenant_id, $user->id, 'subscription', (float)$payment->original_amount, $to, ['plan_id'=>$sub->membership_plan_id]);
        return redirect()->route('web.pay.show', ['orderNo'=>$new->order_no]);
    }

    public function me(Request $r)
    {
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录');
        $tid = $tenant? $tenant->id : ( \App\Models\Tenant::where('slug','demo')->value('id') ?? 1);
        $profile = \App\Models\MemberProfile::with(['level','branch','tenant'])->where('tenant_id',$tid)->where('user_id',$user->id)->first();
        // 若无档案（历史账号）则现场补建普通会员
        if (!$profile) {
            $level = \App\Models\MembershipLevel::where('tenant_id',$tid)->where('is_default',true)->first()
                ?? \App\Models\MembershipLevel::where('tenant_id',$tid)->orderBy('level')->first();
            $branchId = \App\Models\Branch::where('tenant_id',$tid)->value('id');
            $profile = \App\Models\MemberProfile::create([
                'user_id'=>$user->id,'tenant_id'=>$tid,'branch_id'=>$branchId,
                'membership_level_id'=>$level?->id,'status'=>'active',
            ]);
            \App\Models\Wallet::firstOrCreate(['tenant_id'=>$tid,'user_id'=>$user->id], ['balance'=>0]);
            $profile->load(['level','branch','tenant']);
        }
        $wallet = \App\Models\Wallet::where('tenant_id',$tid)->where('user_id',$user->id)->first();
        $walletTx = $wallet ? \App\Models\WalletTransaction::where('wallet_id',$wallet->id)->orderByDesc('id')->limit(8)->get() : collect();
        $levels = \App\Models\MembershipLevel::where('tenant_id',$tid)->orderBy('level')->get();
        $subs = \App\Models\Subscription::with('plan')->where('tenant_id',$tid)->where('user_id',$user->id)->orderByDesc('id')->limit(6)->get();
        $orders = \App\Models\Order::with(['shop','items'])->where('tenant_id',$tid)->where('user_id',$user->id)->orderByDesc('id')->limit(5)->get();
        $ledgers = \App\Models\PointLedger::where('tenant_id',$tid)->where('user_id',$user->id)->orderByDesc('id')->limit(8)->get();
        // 下一级进度
        $nextLevel = $levels->firstWhere('level', '>', $profile->level?->level ?? 0);
        $progress = null;
        if ($nextLevel && $profile->level) {
            $need = max(1, $nextLevel->min_growth - $profile->level->min_growth);
            $have = max(0, $profile->growth - $profile->level->min_growth);
            $progress = min(100, round($have / $need * 100));
        }
        // 签到状态
        $todayDone = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at', today())->exists();
        $streak = 0;
        $cursor = $todayDone ? today() : today()->subDay();
        while(true){
            $has = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at', $cursor)->exists();
            if (!$has) break;
            $streak++; $cursor = $cursor->subDay();
            if ($streak>60) break;
        }
        return view('shop.me', compact('tenant','user','profile','wallet','walletTx','levels','subs','orders','ledgers','progress','nextLevel','todayDone','streak'));
    }

    public function updateMe(Request $r)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login');
        $data = $r->validate([
            'name'=>'nullable|string|max:50',
            'real_name'=>'nullable|string|max:50',
            'phone'=>'nullable|string|max:20',
            'gender'=>'nullable|in:unknown,male,female',
            'birthday'=>'nullable|date',
        ]);
        if (!empty($data['name'])) $user->update(['name'=>$data['name']]);
        $tenant = $this->tenant($r);
        $tid = $tenant? $tenant->id : ( \App\Models\Tenant::where('slug','demo')->value('id') ?? 1);
        $profile = \App\Models\MemberProfile::where('tenant_id',$tid)->where('user_id',$user->id)->first();
        if ($profile) {
            $up = collect($data)->only(['real_name','phone','gender','birthday'])->filter(fn($v)=>$v!==null)->toArray();
            if (!empty($up)) $profile->update($up);
        }
        return back()->with('success','资料已更新');
    }

    public function checkIn(Request $r, \App\Services\MembershipService $svc)
    {
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录再签到');
        $tid = $tenant? $tenant->id : ( \App\Models\Tenant::where('slug','demo')->value('id') ?? 1);
        // 确保有档案
        $profile = \App\Models\MemberProfile::where('tenant_id',$tid)->where('user_id',$user->id)->first();
        if (!$profile) {
            $level = \App\Models\MembershipLevel::where('tenant_id',$tid)->where('is_default',true)->first()
                ?? \App\Models\MembershipLevel::where('tenant_id',$tid)->orderBy('level')->first();
            $branchId = \App\Models\Branch::where('tenant_id',$tid)->value('id');
            $profile = \App\Models\MemberProfile::create([
                'user_id'=>$user->id,'tenant_id'=>$tid,'branch_id'=>$branchId,
                'membership_level_id'=>$level?->id,'status'=>'active',
            ]);
            \App\Models\Wallet::firstOrCreate(['tenant_id'=>$tid,'user_id'=>$user->id], ['balance'=>0]);
        }
        // 风控：同 IP 每日签到总次数限制（防脚本批量刷号）， NAT 环境请调大 config membership.checkin_ip_daily_limit
        $ipLimit = (int) config('membership.checkin_ip_daily_limit', 20);
        $ipKey = 'checkin:'.$tid.':'.today()->toDateString().':'.md5($r->ip() ?? '0.0.0.0');
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            $msg = '该网络今日签到次数已达上限，如有疑问请联系商户';
            if ($r->expectsJson()) return response()->json(['message'=>$msg], 429);
            return back()->with('error', $msg);
        }
        // 当日是否已签
        $todayDone = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)
            ->whereDate('checked_in_at', today())->exists();
        if ($todayDone) {
            if ($r->expectsJson()) return response()->json(['message'=>'今日已签到'], 422);
            return back()->with('error','今日已签到，明天再来');
        }
        // 计算连击：从昨天往前连续天数
        $streak = 0;
        $cursor = today()->subDay();
        while(true){
            $has = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)
                ->whereDate('checked_in_at', $cursor)->exists();
            if (!$has) break;
            $streak++; $cursor = $cursor->subDay();
            if ($streak>60) break;
        }
        $newStreak = $streak + 1;
        // 奖励：基础10 + 连击加成
        $points = 10;
        $bonus = 0;
        if ($newStreak >= 30) $bonus = 100;
        elseif ($newStreak >= 15) $bonus = 50;
        elseif ($newStreak >= 7) $bonus = 20;
        elseif ($newStreak >= 3) $bonus = 5;
        $points += $bonus;

        $checkIn = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function() use ($tid,$user,$profile,$points,$bonus,$newStreak,$svc,&$checkIn){
                $branchId = $profile->branch_id ?? \App\Models\Branch::where('tenant_id',$tid)->value('id');
                $checkIn = \App\Models\CheckIn::create([
                    'tenant_id'=>$tid,'branch_id'=>$branchId,'user_id'=>$user->id,
                    'checked_in_at'=>now(),'checked_on'=>today(),'method'=>'web',
                ]);
                $svc->addPoints($tid,$user->id,$points,'earn',"每日签到 +{$points}分".($bonus? "（连签{$newStreak}天加{$bonus}）":''),'check_in',$checkIn->id);
                $profile->update(['last_active_at'=>now()]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // (tenant,user,checked_on) 唯一键兜底：并发下同日重复签到直接拒绝
            $msg = '今日已签到，明天再来';
            if ($r->expectsJson()) return response()->json(['message'=>$msg], 422);
            return back()->with('error', $msg);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($ipKey, 86400);

        $msg = "签到成功 +{$points}积分，已连签{$newStreak}天".($bonus? "（含连击+{$bonus}）":'');
        if ($r->expectsJson()) return response()->json(['ok'=>true,'points'=>$points,'streak'=>$newStreak,'check_in'=>$checkIn]);
        return back()->with('success',$msg);
    }

    public function checkInPage(Request $r)
    {
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login');
        $tid = $tenant? $tenant->id : ( \App\Models\Tenant::where('slug','demo')->value('id') ?? 1);
        $profile = \App\Models\MemberProfile::with('level')->where('tenant_id',$tid)->where('user_id',$user->id)->first();
        $todayDone = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at', today())->exists();
        $streak = 0; $cursor = today()->subDay();
        // 若今日已签则从今日算，否则从昨天算
        $start = $todayDone ? today() : today()->subDay();
        $cursor = $start;
        while(true){
            $has = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at', $cursor)->exists();
            if (!$has) break;
            $streak++; $cursor = $cursor->subDay();
            if ($streak>60) break;
        }
        $history = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->orderByDesc('checked_in_at')->limit(30)->get();
        $calendar = [];
        for($i=6;$i>=0;$i--){
            $d = today()->subDays($i);
            $done = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at',$d)->exists();
            $calendar[] = ['date'=>$d->format('m-d'),'week'=>$d->format('D'),'done'=>$done,'isToday'=>$d->isToday()];
        }
        return view('shop.checkin', compact('tenant','user','profile','todayDone','streak','history','calendar'));
    }
}
