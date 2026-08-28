<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            // 裸域落地页
            $plans = MembershipPlan::with('features')->where('is_active', true)->orderBy('price')->limit(3)->get();
            // 若多租户无数据则回退 demo
            if ($plans->isEmpty()) $plans = MembershipPlan::with('features')->where('tenant_id', 1)->where('is_active', true)->orderBy('price')->get();
            return view('landing', compact('plans','tenant'));
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
        return view('shop.checkout', compact('tenant'));
    }

    public function placeOrder(Request $r, PaymentService $paySvc)
    {
        $data = $r->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'channel' => 'nullable|string|in:mock,wallet,wechat,alipay,stripe,manual',
            'address' => 'nullable|array',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
        ]);
        $tenant = $this->tenant($r);
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error', '请先登录');

        $shop = Shop::findOrFail($data['shop_id']);
        if ($tenant && $shop->tenant_id !== $tenant->id) return back()->withErrors(['shop_id' => '店铺不属于当前商户']);
        if ($shop->status !== 'active') return back()->withErrors(['shop_id' => '店铺已关闭']);

        try {
            $result = DB::transaction(function () use ($data, $user, $shop, $paySvc) {
                $orderNo = 'ORD'.date('YmdHis').strtoupper(Str::random(6));
                $total = 0;
                $itemsData = [];
                foreach ($data['items'] as $it) {
                    $p = Product::where('shop_id', $shop->id)->lockForUpdate()->findOrFail($it['product_id']);
                    if ($p->status !== 'on_sale') throw new \RuntimeException("商品 {$p->name} 已下架");
                    if ($p->stock < $it['quantity']) throw new \RuntimeException("商品 {$p->name} 库存不足");
                    $p->decrement('stock', $it['quantity']);
                    $amount = round((float)$p->price * $it['quantity'], 2);
                    $total += $amount;
                    $itemsData[] = ['product'=>$p,'quantity'=>$it['quantity'],'amount'=>$amount];
                }
                $platformFee = round($total * (float)$shop->platform_fee_rate, 2);
                $order = Order::create([
                    'tenant_id'=>$shop->tenant_id,
                    'shop_id'=>$shop->id,
                    'user_id'=>$user->id,
                    'order_no'=>$orderNo,
                    'status'=>'pending',
                    'total_amount'=>$total,
                    'discount_amount'=>0,
                    'pay_amount'=>$total,
                    'platform_fee'=>$platformFee,
                    'payment_channel'=>$data['channel'] ?? 'mock',
                    'address'=>$data['address'] ?? null,
                ]);
                foreach ($itemsData as $d) {
                    \App\Models\OrderItem::create([
                        'order_id'=>$order->id,
                        'product_id'=>$d['product']->id,
                        'product_name'=>$d['product']->name,
                        'price'=>$d['product']->price,
                        'quantity'=>$d['quantity'],
                        'amount'=>$d['amount'],
                    ]);
                }
                $channel = $data['channel'] ?? config('payments.default_channel','mock');
                $payment = $paySvc->create(
                    $shop->tenant_id, $user->id, 'order', (float)$order->pay_amount, $channel,
                    ['business_id'=>$order->id,'subject'=>"商城订单 {$order->order_no}",'coupon_id'=>$data['coupon_id'] ?? null,'original_amount'=>(float)$total,'meta'=>['order_no'=>$order->order_no,'shop_id'=>$shop->id]]
                );
                $order->update(['payment_order_no'=>$payment->order_no]);
                return ['order'=>$order->fresh()->load('items','shop'),'payment'=>$payment];
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('web.orders.show', $result['order']->order_no)->with('success', '下单成功，订单号 '.$result['order']->order_no);
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

    public function subscribePlan(Request $r, \App\Services\MembershipService $svc)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('web.login')->with('error','请先登录再订阅');
        $data = $r->validate(['plan_id'=>'required|integer|exists:membership_plans,id']);
        $tenant = $this->tenant($r);
        $tid = $tenant? $tenant->id : 1;
        $plan = MembershipPlan::where('tenant_id',$tid)->findOrFail($data['plan_id']);
        try {
            $sub = $svc->subscribe($tid, $user->id, $plan->id, ['payment_method'=>'mock','paid_amount'=>$plan->price]);
        } catch (\Throwable $e) {
            return back()->withErrors(['plan_id'=>$e->getMessage()]);
        }
        return redirect()->route('web.pricing')->with('success','订阅成功：'.$plan->name.'（'.$sub->order_no.'）已激活');
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
        \Illuminate\Support\Facades\DB::transaction(function() use ($tid,$user,$profile,$points,$bonus,$newStreak,$svc,&$checkIn){
            $branchId = $profile->branch_id ?? \App\Models\Branch::where('tenant_id',$tid)->value('id');
            $checkIn = \App\Models\CheckIn::create([
                'tenant_id'=>$tid,'branch_id'=>$branchId,'user_id'=>$user->id,
                'checked_in_at'=>now(),'method'=>'web',
            ]);
            $svc->addPoints($tid,$user->id,$points,'earn',"每日签到 +{$points}分".($bonus? "（连签{$newStreak}天加{$bonus}）":''),'check_in',$checkIn->id);
            $profile->update(['last_active_at'=>now()]);
        });

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
