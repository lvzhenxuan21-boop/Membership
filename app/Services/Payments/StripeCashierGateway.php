<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Stripe 网关 - 兼顾 Cashier 与原生 stripe-php
 * - 若安装 Cashier 且配置 STRIPE_SECRET，则走 Stripe Checkout Session
 * - 未配置时降级 Mock，保证演示可跑
 * - 兼顾：保留 Cashier Billable 能力，买家可用 Cashier 管理订阅/发票
 */
class StripeCashierGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        if ($this->isMockMode()) {
            Log::warning('[StripeCashierGateway] STRIPE_SECRET 未配置，降级 Mock');
            return (new MockGateway)->pay($payment);
        }

        try {
            $stripe = new StripeClient($this->secret());
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                // 支付完成后 Stripe 重定向回支付页（该页可主动 query 对账）；真实核销以 webhook 为准
                'success_url' => url("/pay/{$payment->order_no}"),
                'cancel_url' => url("/pay/{$payment->order_no}"),
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($payment->currency ?? 'cny'),
                        'product_data' => ['name' => $payment->subject],
                        'unit_amount' => (int)round((float)$payment->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => $payment->order_no,
                'metadata' => ['order_no' => $payment->order_no, 'tenant_id' => $payment->tenant_id],
            ]);

            $payment->update(['channel_data' => array_merge($payment->channel_data ?? [], [
                'stripe_session_id'=>$session->id, 'stripe_url'=>$session->url,
                'stripe_payment_intent'=>$session->payment_intent ?? null,
            ])]);

            return [
                'pay_url' => $session->url,
                'channel' => 'stripe',
                'stripe_session_id' => $session->id,
                'stripe' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('[StripeCashierGateway] 创建 Session 失败: '.$e->getMessage(), ['order_no'=>$payment->order_no]);
            // 失败降级 Mock，保证流程不断
            return [
                'pay_url' => url("/api/v1/payment/{$payment->order_no}/mock-pay"),
                'channel' => 'stripe',
                'mock_fallback' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool
    {
        if ($this->isMockMode()) return true; // 演示模式不校验
        $webhookSecret = $this->webhookSecret();
        // 真实渠道必须验签：未配置 webhook 密钥时拒绝回调，防止伪造通知核销支付单
        if (empty($webhookSecret)) {
            Log::error('[StripeCashierGateway] 已配置 STRIPE_SECRET 但缺少 STRIPE_WEBHOOK_SECRET，拒绝未验签回调');
            return false;
        }
        if (empty($signature)) return false;
        try {
            \Stripe\Webhook::constructEvent($rawBody ?? json_encode($payload), $signature, $webhookSecret);
            return true;
        } catch (\Throwable $e) {
            Log::error('[StripeCashierGateway] webhook 验签失败: '.$e->getMessage());
            return false;
        }
    }

    public function query(Payment $payment): array
    {
        $secret = $this->secret();
        if (empty($secret) || empty($payment->channel_data['stripe_session_id'] ?? null)) {
            return ['status'=>$payment->status, 'channel'=>'stripe', 'mock'=>true];
        }
        try {
            $stripe = new StripeClient($secret);
            $session = $stripe->checkout->sessions->retrieve($payment->channel_data['stripe_session_id']);
            return ['status'=>$session->payment_status, 'stripe_session'=>$session->toArray()];
        } catch (\Throwable $e) {
            return ['status'=>$payment->status, 'error'=>$e->getMessage()];
        }
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        if ($this->isMockMode()) return (new MockGateway)->refund($payment, $amount);

        try {
            $stripe = new StripeClient($this->secret());
            // 从 Checkout Session 取 payment_intent（支付完成后必存在），优先用此前缓存的值
            $pi = $payment->channel_data['stripe_payment_intent'] ?? null;
            if (empty($pi) && !empty($payment->channel_data['stripe_session_id'])) {
                $session = $stripe->checkout->sessions->retrieve($payment->channel_data['stripe_session_id']);
                $pi = $session->payment_intent ?? null;
                if ($pi) {
                    $payment->update(['channel_data' => array_merge($payment->channel_data ?? [], ['stripe_payment_intent'=>$pi])]);
                }
            }
            if (empty($pi)) {
                return ['success'=>false, 'error'=>'缺少 payment_intent：会话尚未完成支付或缺少会话信息，无法退款'];
            }

            $params = ['payment_intent' => $pi];
            if ($amount !== null && (float)$amount < (float)$payment->amount) {
                $params['amount'] = (int)round((float)$amount * 100); // 部分退款才传 amount，默认全额
            }
            $refund = $stripe->refunds->create($params);
            $payment->update(['channel_data' => array_merge($payment->channel_data ?? [], ['stripe_refund_id'=>$refund->id])]);
            return ['success'=>true, 'channel'=>'stripe', 'refund_id'=>$refund->id, 'status'=>$refund->status, 'refund_amount'=>$amount ?? $payment->amount];
        } catch (\Throwable $e) {
            Log::error('[StripeCashierGateway] 退款失败: '.$e->getMessage(), ['order_no'=>$payment->order_no]);
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }

    // 未配置 STRIPE_SECRET 时视为 Mock 模式（与 pay() 降级条件保持一致）
    public function isMockMode(): bool
    {
        return empty($this->secret());
    }

    // 密钥解析：依次取 cashier 配置 / payments 配置 / 环境变量，跳过空串（vendor 配置里 env 未设时是 '' 而非 null，?? 链会短路）
    private function secret(): ?string
    {
        foreach ([config('cashier.secret'), config('payments.channels.stripe.secret'), env('STRIPE_SECRET')] as $candidate) {
            if (!empty($candidate)) return (string)$candidate;
        }
        return null;
    }

    private function webhookSecret(): ?string
    {
        foreach ([config('cashier.webhook.secret'), config('payments.channels.stripe.webhook_secret'), env('STRIPE_WEBHOOK_SECRET')] as $candidate) {
            if (!empty($candidate)) return (string)$candidate;
        }
        return null;
    }
}
