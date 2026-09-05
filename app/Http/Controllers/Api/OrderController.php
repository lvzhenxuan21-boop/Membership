<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;

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
        $p = Product::with('shop')->where('status','on_sale')->findOrFail($id);
        return response()->json($p);
    }

    // 创建订单 - auth:sanctum（下单逻辑统一在 OrderService）
    public function create(Request $r, OrderService $orderSvc)
    {
        $data = $r->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'channel' => 'nullable|string|in:mock,wallet,wechat,alipay,stripe,manual',
            'address' => 'nullable|array',
            'use_points' => 'nullable|boolean',
        ]);

        $tenant = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r);
        try {
            $order = $orderSvc->placeOrder(
                $r->user(), $tenant, $data['shop_id'], $data['items'],
                $data['channel'] ?? null, $data['coupon_id'] ?? null, $data['address'] ?? null,
                (bool)($data['use_points'] ?? false),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }

        $payment = Payment::where('order_no', $order->payment_order_no)->first();
        return response()->json(['order'=>$order, 'payment'=>$payment], 201);
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
