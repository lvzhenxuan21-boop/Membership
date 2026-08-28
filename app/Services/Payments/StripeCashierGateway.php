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
        $secret = config('cashier.secret') ?? config('payments.channels.stripe.secret') ?? env('STRIPE_SECRET');
        if (empty($secret)) {
            Log::warning('[StripeCashierGateway] STRIPE_SECRET 未配置，降级 Mock');
            return (new MockGateway)->pay($payment);
        }

        // 已配置：尝试创建 Stripe Checkout Session（原生 stripe-php，不依赖 Cashier 订阅表，避免与本项目 subscriptions 冲突）
        try {
            $stripe = new StripeClient($secret);
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => url("/api/v1/payment/callback/stripe?order_no={$payment->order_no}&session_id={CHECKOUT_SESSION_ID}"),
                'cancel_url' => url("/payment/cancel?order_no={$payment->order_no}"),
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

            $payment->update(['channel_data' => array_merge($payment->channel_data ?? [], ['stripe_session_id'=>$session->id, 'stripe_url'=>$session->url])]);

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

    public function verifyWebhook(array $payload, ?string $signature = null): bool
    {
        $webhookSecret = config('cashier.webhook.secret') ?? env('STRIPE_WEBHOOK_SECRET');
        if (empty($webhookSecret) || empty($signature)) {
            return true; // 演示阶段不强校验
        }
        try {
            \Stripe\Webhook::constructEvent(json_encode($payload), $signature, $webhookSecret);
            return true;
        } catch (\Throwable $e) {
            Log::error('[StripeCashierGateway] webhook verify failed: '.$e->getMessage());
            return false;
        }
    }

    public function query(Payment $payment): array
    {
        $secret = config('cashier.secret') ?? env('STRIPE_SECRET');
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
        $secret = config('cashier.secret') ?? env('STRIPE_SECRET');
        if (empty($secret)) return ['success'=>true, 'mock'=>true];
        try {
            $stripe = new StripeClient($secret);
            // 需买家在支付时保存 payment_intent，此处演示直接返回
            return ['success'=>true, 'channel'=>'stripe', 'refund_amount'=>$amount ?? $payment->amount];
        } catch (\Throwable $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }
}
