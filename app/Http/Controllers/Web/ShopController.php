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

    /**
     * 当前商户 ID：优先租户解析，其次兜底默认商户（MEMBERSHIP_DEFAULT_TENANT_SLUG，默认 demo）。
     * 找不到返回 null——绝不魔法回退到 id=1，避免绑错租户。
     */
    private function tenantId(Request $r): ?int
    {
        if ($tenant = $this->tenant($r)) return (int) $tenant->id;
        $slug = config('membership.default_tenant_slug', 'demo');
        if (!$slug) return null;
        $id = \App\Models\Tenant::where('slug', $slug)->where('status', 'active')->value('id');
        return $id ? (int) $id : null;
    }

    // 连签天数：一次查询取最近签到日，按日历连续性计数（替代逐天 exists 的 N 次查询）
    private function computeStreak(int $tenantId, int $userId, bool $todayDone): int
    {
        $start = $todayDone ? today() : today()->subDay();
        $dates = \App\Models\CheckIn::where('tenant_id',$tenantId)->where('user_id',$userId)
            ->where('checked_on','>=', $start->copy()->subDays(60)->toDateString())
            ->orderByDesc('checked_on')
            ->limit(61)
            ->pluck('checked_on');
        $streak = 0;
        $cursor = $start->copy();
        foreach ($dates as $d) {
            if (!$d->isSameDay($cursor)) break;
            $streak++;
            $cursor = $cursor->subDay();
        }
        return $streak;
    }

    // 首页：有 tenant → 商城，无 tenant → SaaS 落地页
    public function index(Request $r)
    {
        $tenant = $this->tenant($r);
        // path 兼容 /shop/{slug}（只认在营租户）
        if (!$tenant && $r->is('shop/*')) {
            $slug = explode('/', trim($r->path(), '/'))[1] ?? null;
            if ($slug) $tenant = \App\Models\Tenant::where('slug', $slug)->where('status', 'active')->first();
        }

        if (!$tenant) {
            // 裸域获客首页
            $stores = \App\Models\Tenant::where('status', 'active')->orderByDesc('id')->take(8)->get();
            $storeCount = \App\Models\Tenant::where('status', 'active')->count();
            $demoStore = \App\Models\Tenant::where('slug', 'demo')->where('status', 'active')->first() ?? $stores->first();
            // Hero 预览卡展示在售商品实图
            $samples = \App\Models\Product::with('shop')->where('status', 'on_sale')->whereNotNull('cover')->orderByDesc('sales')->take(3)->get();
            // 「示例店铺」入口：优先用线上演示地址（.env MEMBERSHIP_DEMO_STORE_URL），否则本地 demo 店
            $demoUrl = config('membership.demo_store_url') ?: ($demoStore ? '/shop/'.$demoStore->slug : null);
            $demoLabel = $demoUrl ? str_replace(['https://', 'http://'], '', $demoUrl) : null;
            return view('landing', compact('stores', 'storeCount', 'demoStore', 'samples', 'demoUrl', 'demoLabel'));
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
        $product = Product::with('shop')->where('status', 'on_sale')->findOrFail($id);
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
        $tid = $this->tenantId($r);
        $user = Auth::user();
        $points = ($user && $tid)
            ? (int) (\App\Models\MemberProfile::where('tenant_id',$tid)->where('user_id',$user->id)->value('points') ?? 0)
            : 0;
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
        $tid = $this->tenantId($r);
        $plans = $tid
            ? MembershipPlan::with('features')->where('tenant_id', $tid)->where('is_active', true)->orderBy('price')->get()
            : collect();
        return view('shop.pricing', compact('plans','tenant'));
    }

    public function subscribePlan(Request $r, PaymentService $paySvc)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录再订阅');
        $data = $r->validate(['plan_id'=>'required|integer|exists:membership_plans,id']);
        $tenant = $this->tenant($r);
        $tid = $this->tenantId($r);
        if (!$tid) return back()->withErrors(['plan_id'=>'无法识别商户，请从商户店铺页面进入']);
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
        // 同一事务内"取消旧单 + 重开新单"：中途失败整体回滚，旧支付单保持 pending；优惠券跨渠道继承
        $new = \Illuminate\Support\Facades\DB::transaction(function () use ($paySvc, $payment, $user, $to, $sub) {
            $paySvc->cancel($payment);
            return $paySvc->create($payment->tenant_id, $user->id, 'subscription', (float)$payment->original_amount, $to, [
                'plan_id'=>$sub->membership_plan_id,
                'coupon_id'=>$payment->coupon_id,
            ]);
        });
        return redirect()->route('web.pay.show', ['orderNo'=>$new->order_no]);
    }

    public function me(Request $r)
    {
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录');
        $tid = $this->tenantId($r);
        if (!$tid) return redirect('/')->with('error','请从商户店铺访问会员中心');
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
        $streak = $this->computeStreak($tid, $user->id, $todayDone);
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
        $tid = $this->tenantId($r);
        if (!$tid) return back()->with('error','无法识别商户');
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
        $tid = $this->tenantId($r);
        if (!$tid) return redirect('/')->with('error','请从商户店铺访问签到页');
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
        $streak = $this->computeStreak($tid, $user->id, false);
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
        $tid = $this->tenantId($r);
        if (!$tid) return redirect('/')->with('error','请从商户店铺访问签到页');
        $profile = \App\Models\MemberProfile::with('level')->where('tenant_id',$tid)->where('user_id',$user->id)->first();
        $todayDone = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->whereDate('checked_in_at', today())->exists();
        $streak = $this->computeStreak($tid, $user->id, $todayDone);
        $history = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)->orderByDesc('checked_in_at')->limit(30)->get();
        // 近 7 天日历：一次查询代替 7 次 exists
        $doneDates = \App\Models\CheckIn::where('tenant_id',$tid)->where('user_id',$user->id)
            ->where('checked_on','>=', today()->subDays(6)->toDateString())
            ->pluck('checked_on')
            ->map(fn($d) => $d->toDateString())
            ->all();
        $calendar = [];
        for($i=6;$i>=0;$i--){
            $d = today()->subDays($i);
            $calendar[] = ['date'=>$d->format('m-d'),'week'=>$d->format('D'),'done'=>in_array($d->toDateString(), $doneDates, true),'isToday'=>$d->isToday()];
        }
        return view('shop.checkin', compact('tenant','user','profile','todayDone','streak','history','calendar'));
    }
}
