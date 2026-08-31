<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $svc) {}

    private function isAdmin($user): bool
    {
        return $user && $user->hasAnyRole(['super_admin','admin','tenant_admin']);
    }

    // 本人支付单或管理员可见/可操作
    private function authorizePayment(Request $r, Payment $payment): void
    {
        $user = $r->user();
        if (!$user) abort(401, 'Unauthenticated');
        if ($payment->user_id !== $user->id && !$this->isAdmin($user)) {
            abort(403, '无权操作该支付单');
        }
    }

    // 创建支付单（订阅/充值通用）：user_id 以登录态为准，仅管理员可代其他用户支付
    public function create(Request $r)
    {
        $data = $r->validate([
            'tenant_id' => 'required|integer',
            'user_id' => 'nullable|integer',
            'business_type' => 'required|in:subscription,wallet_recharge,order,other',
            'amount' => 'nullable|numeric|min:0',
            'channel' => 'nullable|in:mock,wechat,alipay,stripe,wallet,manual',
            'plan_id' => 'nullable|integer',
            'coupon_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:100',
        ]);

        $user = $r->user();
        $userId = $user->id;
        if (isset($data['user_id']) && (int)$data['user_id'] !== $userId) {
            if (!$this->isAdmin($user)) {
                return response()->json(['message' => '无权为其他用户创建支付单'], 403);
            }
            $userId = (int)$data['user_id'];
        }

        // 订阅类型金额与标题由 PaymentService 按套餐价强制计算，客户端传入的 amount 对订阅无效（防改价）

        try {
            $payment = $this->svc->create(
                $data['tenant_id'],
                $userId,
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
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            // 金额/渠道/优惠券校验失败（含配额用尽）对调用方是可重试的业务错误
            return response()->json(['message'=>$e->getMessage()], 422);
        }

        return response()->json($payment, 201);
    }

    public function show(Request $r, string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $this->authorizePayment($r, $payment);
        return response()->json($payment->load(['user','tenant','coupon']));
    }

    public function query(Request $r, string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $this->authorizePayment($r, $payment);
        $gatewayData = $this->svc->query($payment);
        return response()->json(['payment'=>$payment, 'gateway'=>$gatewayData]);
    }

    // 演示用：模拟支付成功（仅限本人的支付单 + 当前运行在 Mock 模式的渠道）
    public function mockPay(Request $r, string $orderNo)
    {
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        $this->authorizePayment($r, $payment);
        if ($payment->isPaid()) return response()->json(['ok'=>true,'already_paid'=>true,'payment'=>$payment]);
        if (!\App\Services\Payments\PaymentGatewayFactory::mockAllowed()) {
            return response()->json(['ok'=>false,'message'=>'生产环境已禁用模拟支付，请走真实支付流程'], 422);
        }
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
        $this->authorizePayment($r, $payment);
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

    // 支付单列表：普通用户只能看自己的；租户管理员限定本租户；平台管理员可筛选
    public function list(Request $r)
    {
        $user = $r->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'], 401);
        $q = Payment::query()->with(['user','tenant']);
        if (!$this->isAdmin($user)) {
            $q->where('user_id', $user->id);
        } else {
            if ($r->filled('tenant_id')) $q->where('tenant_id',$r->input('tenant_id'));
            if ($r->filled('user_id')) $q->where('user_id',$r->input('user_id'));
            // tenant_admin 只能看本租户
            if ($user->hasRole('tenant_admin') && !$user->hasAnyRole(['super_admin','admin']) && $user->tenant_id) {
                $q->where('tenant_id', $user->tenant_id);
            }
        }
        if ($r->filled('status')) $q->where('status',$r->input('status'));
        if ($r->filled('channel')) $q->where('channel',$r->input('channel'));
        return $q->latest()->paginate(min(100, max(1, (int)$r->input('per_page',15))));
    }
}
