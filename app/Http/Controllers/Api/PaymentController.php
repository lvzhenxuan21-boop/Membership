<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $svc) {}

    // 创建支付单（订阅/充值通用）
    public function create(Request $r)
    {
        $data = $r->validate([
            'tenant_id' => 'required|integer',
            'user_id' => 'required|integer',
            'business_type' => 'required|in:subscription,wallet_recharge,order,other',
            'amount' => 'nullable|numeric|min:0',
            'channel' => 'nullable|in:mock,wechat,alipay,stripe,wallet,manual',
            'plan_id' => 'nullable|integer',
            'coupon_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:100',
        ]);

        // 订阅类型金额与标题由 PaymentService 按套餐价强制计算，客户端传入的 amount 对订阅无效（防改价）

        $payment = $this->svc->create(
            $data['tenant_id'],
            $data['user_id'],
            $data['business_type'],
            (float)($data['amount'] ?? 0),
            $data['channel'] ?? config('payments.default_channel','mock'),
            [
                'plan_id'=>$data['plan_id'] ?? null,
                'coupon_id'=>$data['coupon_id'] ?? null,
                'subject'=>$data['subject'] ?? null,
                'meta'=>$r->input('meta'),
            ]
        );

        return response()->json($payment, 201);
    }

    public function show(string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        return response()->json($payment->load(['user','tenant','coupon']));
    }

    public function query(string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $gatewayData = $this->svc->query($payment);
        return response()->json(['payment'=>$payment, 'gateway'=>$gatewayData]);
    }

    // 演示用：模拟支付成功（仅限当前运行在 Mock 模式的渠道，防止 mock-pay 绕过真实渠道）
    public function mockPay(string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        if ($payment->isPaid()) return response()->json(['ok'=>true,'already_paid'=>true,'payment'=>$payment]);
        $gateway = \App\Services\Payments\PaymentGatewayFactory::make($payment->channel);
        if (!$gateway->isMockMode()) {
            return response()->json(['ok'=>false,'message'=>"渠道 {$payment->channel} 未运行在 Mock 模式，请走真实支付流程"], 422);
        }
        try {
            $payment = $this->svc->markPaid($payment);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 422); // 已取消/已过期等
        }
        return response()->json(['ok'=>true,'payment'=>$payment]);
    }

    // 网关回调统一入口
    public function callback(Request $r, string $channel)
    {
        if (!in_array($channel, ['wechat','alipay','stripe','mock'])) $channel='mock';
        try {
            $payment = $this->svc->handleCallback(
                $channel,
                $r->all(),
                $r->header('Stripe-Signature') ?? $r->header('stripe-signature'),
                $r->getContent(), // 验签必须用原始请求体
            );
            return response()->json(['ok'=>true,'payment'=>$payment]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 422);
        }
    }

    public function stripeWebhook(Request $r)
    {
        return $this->callback($r, 'stripe');
    }

    // 取消支付单：本人或管理员（订单业务会连带取消订单并回补库存）
    public function cancel(Request $r, string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        // 路由无 auth:sanctum 中间件，需显式从 sanctum guard 解析 Bearer token（web session 兜底）
        $user = $r->user('sanctum') ?? $r->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'], 401);
        if ($payment->user_id !== $user->id && !$user->hasAnyRole(['super_admin','admin','tenant_admin'])) {
            return response()->json(['message'=>'无权操作该支付单'], 403);
        }
        $payment = $this->svc->cancel($payment);
        return response()->json($payment);
    }

    public function refund(Request $r, string $orderNo)
    {
        $data = $r->validate(['amount'=>'nullable|numeric|min:0.01']);
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $payment = $this->svc->refund($payment, isset($data['amount']) ? (float)$data['amount'] : null);
        return response()->json($payment);
    }

    // 管理员手动标记已支付（线下/手动渠道）
    public function markPaid(string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $payment = $this->svc->markPaid($payment);
        return response()->json($payment);
    }

    public function list(Request $r)
    {
        $q = Payment::query()->with(['user','tenant']);
        if ($r->filled('tenant_id')) $q->where('tenant_id',$r->input('tenant_id'));
        if ($r->filled('user_id')) $q->where('user_id',$r->input('user_id'));
        if ($r->filled('status')) $q->where('status',$r->input('status'));
        if ($r->filled('channel')) $q->where('channel',$r->input('channel'));
        return $q->latest()->paginate($r->input('per_page',15));
    }
}
