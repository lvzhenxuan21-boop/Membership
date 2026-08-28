<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // 商品列表 - 支持子域名自动 tenant
    public function products(Request $r)
    {
        $tenant = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r);
        $q = Product::with('shop')->where('status','on_sale');
        if ($tenant) $q->where('tenant_id', $tenant->id);
        if ($r->filled('shop_id')) $q->where('shop_id', $r->shop_id);
        if ($r->filled('keyword')) $q->where('name','like','%'.$r->keyword.'%');
        return response()->json($q->orderByDesc('id')->paginate(12));
    }

    public function productShow(Request $r, int $id)
    {
        $p = Product::with('shop')->findOrFail($id);
        return response()->json($p);
    }

    // 创建订单 - auth:sanctum
    public function create(Request $r, PaymentService $paySvc)
    {
        $data = $r->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'channel' => 'nullable|string|in:mock,wallet,wechat,alipay,stripe,manual',
            'address' => 'nullable|array',
        ]);

        $tenant = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r);
        $user = $r->user();
        $shop = Shop::findOrFail($data['shop_id']);
        if ($tenant && $shop->tenant_id !== $tenant->id) return response()->json(['message'=>'店铺不属于当前商户'], 422);
        if ($shop->status !== 'active') return response()->json(['message'=>'店铺已关闭'], 422);

        return DB::transaction(function () use ($data, $user, $shop, $paySvc) {
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

            $discount = 0;
            // 优惠券在 PaymentService 中计算，这里先占位

            $platformFee = round($total * (float)$shop->platform_fee_rate, 2);
            $order = Order::create([
                'tenant_id'=>$shop->tenant_id,
                'shop_id'=>$shop->id,
                'user_id'=>$user->id,
                'order_no'=>$orderNo,
                'status'=>'pending',
                'total_amount'=>$total,
                'discount_amount'=>$discount,
                'pay_amount'=>$total,
                'platform_fee'=>$platformFee,
                'payment_channel'=>$data['channel'] ?? 'mock',
                'address'=>$data['address'] ?? null,
            ]);
            foreach ($itemsData as $d) {
                OrderItem::create([
                    'order_id'=>$order->id,
                    'product_id'=>$d['product']->id,
                    'product_name'=>$d['product']->name,
                    'price'=>$d['product']->price,
                    'quantity'=>$d['quantity'],
                    'amount'=>$d['amount'],
                ]);
            }

            // 创建支付单并关联订单
            $channel = $data['channel'] ?? config('payments.default_channel','mock');
            $payment = $paySvc->create(
                $shop->tenant_id,
                $user->id,
                'order',
                (float)$order->pay_amount,
                $channel,
                [
                    'business_id'=>$order->id,
                    'subject'=>"商城订单 {$order->order_no}",
                    'coupon_id'=>$data['coupon_id'] ?? null,
                    'original_amount'=>(float)$total,
                    'meta'=>['order_no'=>$order->order_no, 'shop_id'=>$shop->id],
                ]
            );
            $order->update(['payment_order_no'=>$payment->order_no]);
            // 若钱包支付已直接成功，order 状态已在 handleOrderPaid 中置为 paid
            $order->refresh();
            // 若 0元或mock_paid，payment已标记paid，order也会被同步

            return response()->json([
                'order'=>$order->load('items','shop'),
                'payment'=>$payment,
            ], 201);
        });
    }

    public function list(Request $r)
    {
        $user = $r->user();
        $q = Order::with(['shop','items'])->where('user_id',$user->id)->orderByDesc('id');
        if ($r->filled('status')) $q->where('status', $r->status);
        // 子域名自动过滤
        if ($t = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r)) {
            $q->where('tenant_id', $t->id);
        }
        return response()->json($q->paginate(15));
    }

    public function show(Request $r, string $orderNo)
    {
        $order = Order::with(['shop','items.product'])->where('order_no',$orderNo)->where('user_id',$r->user()->id)->firstOrFail();
        $payment = \App\Models\Payment::where('order_no', $order->payment_order_no)->first();
        return response()->json(['order'=>$order,'payment'=>$payment]);
    }
}
