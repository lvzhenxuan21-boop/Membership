<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 支付核心服务 - 通用支付 + 兼顾 Cashier(Stripe)
 * 职责：创建支付单 -> 调网关 -> 回调成功后触发业务 (订阅/充值)
 */
class PaymentService
{
    public function __construct(private MembershipService $membershipService) {}

    /**
     * 创建支付单并调起网关
     * $businessType: subscription | wallet_recharge | order
     */
    public function create(int $tenantId, int $userId, string $businessType, float $amount, string $channel, array $opts = []): Payment
    {
        $amount = round($amount, 2);
        if ($amount < 0) throw new \InvalidArgumentException('金额不能为负');
        if (!in_array($channel, PaymentGatewayFactory::channels())) $channel = 'mock';
        if ($amount === 0.0) $channel = 'mock'; // 0元直接成功

        // 订阅类型金额一律以套餐价为准，忽略调用方传入金额（防改价），后续优惠券按套餐价计算
        $plan = null;
        if ($businessType === 'subscription' && !empty($opts['plan_id'])) {
            $plan = MembershipPlan::where('tenant_id',$tenantId)->findOrFail($opts['plan_id']);
            $amount = (float)$plan->price;
            $opts['subject'] = $plan->name;
        }

        // 优惠券抵扣
        $couponId = $opts['coupon_id'] ?? null;
        $discount = 0;
        $originalAmount = $opts['original_amount'] ?? $amount;
        if ($couponId) {
            $coupon = Coupon::where('tenant_id',$tenantId)->findOrFail($couponId);
            $discount = $this->calcCouponDiscount($coupon, $amount, $opts);
            $amount = max(0, round($amount - $discount, 2));
            if ($amount === 0.0) $channel = 'mock';
        }

        return DB::transaction(function () use ($tenantId,$userId,$businessType,$amount,$originalAmount,$discount,$couponId,$channel,$opts,$plan) {
            $orderNo = 'PAY'.date('YmdHis').strtoupper(Str::random(6));
            $subject = $opts['subject'] ?? $this->defaultSubject($businessType, $opts);
            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'order_no' => $orderNo,
                'business_type' => $businessType,
                'business_id' => $opts['business_id'] ?? null,
                'subject' => $subject,
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'discount_amount' => $discount,
                'coupon_id' => $couponId,
                'currency' => $opts['currency'] ?? config('payments.currency','CNY'),
                'channel' => $channel,
                'status' => 'pending',
                'meta' => $opts['meta'] ?? null,
                'expired_at' => now()->addMinutes((int) config('payments.expire_minutes',30)),
            ]);

            // 若是订阅，预创建 pending 订阅并关联（$plan 已在上方按套餐价校验加载）
            if ($plan) {
                $sub = Subscription::create([
                    'tenant_id'=>$tenantId,'user_id'=>$userId,'membership_plan_id'=>$plan->id,
                    'order_no'=>'SUB'.date('YmdHis').strtoupper(Str::random(6)),
                    'status'=>'pending',
                    'payment_method'=>$channel,
                    'payment_id'=>$payment->id,
                    'payment_order_no'=>$payment->order_no,
                    'paid_amount'=>$amount,
                    'meta'=>['payment_order_no'=>$payment->order_no],
                ]);
                $payment->update(['business_id'=>$sub->id]);
            }

            // 钱包充值：business_id 暂空，支付成功后才创建 wallet_transaction

            // 调网关
            $gateway = PaymentGatewayFactory::make($channel);
            try {
                $payData = $gateway->pay($payment);
                // 钱包网关已在内部完成扣款，若返回 wallet_paid 则直接标记成功
                if (!empty($payData['wallet_paid'])) {
                    $payment->update(['channel_data'=>$payData]);
                    $payment = $this->markPaid($payment, $payData['balance_after'] ?? null);
                    return $payment->fresh();
                }
                $payment->update(['pay_url'=>$payData['pay_url'] ?? null, 'channel_data'=>$payData]);
            } catch (\Throwable $e) {
                $payment->update(['channel_data'=>['error'=>$e->getMessage()]]);
                // 不抛异常，让前端可重试；支付单保持 pending
            }

            // 0元直接成功
            if ($amount === 0.0) {
                $this->markPaid($payment);
            }

            return $payment->fresh();
        });
    }

    public function markPaid(Payment $payment, $extra = null): Payment
    {
        if ($payment->isPaid()) return $payment;
        if (!$payment->isPending()) throw new \RuntimeException('仅待支付订单可标记支付');

        return DB::transaction(function () use ($payment, $extra) {
            $payment = Payment::where('id',$payment->id)->lockForUpdate()->first();
            if ($payment->isPaid()) return $payment;
            if ($payment->isExpired()) throw new \RuntimeException('支付已超时');

            $payment->update(['status'=>'paid','paid_at'=>now(),'callback_data'=>['manual_mark'=>true, 'extra'=>$extra]]);

            // 触发业务
            $this->handleBusinessSuccess($payment);

            // 优惠券核销
            if ($payment->coupon_id) {
                Coupon::where('id',$payment->coupon_id)->increment('used_count');
                CouponUser::where('coupon_id',$payment->coupon_id)->where('user_id',$payment->user_id)->where('status','unused')->first()?->update(['status'=>'used','used_at'=>now()]);
            }

            return $payment;
        });
    }

    private function handleBusinessSuccess(Payment $payment): void
    {
        match($payment->business_type) {
            'subscription' => $this->activateSubscription($payment),
            'wallet_recharge' => $this->handleWalletRecharge($payment),
            'order' => $this->handleOrderPaid($payment),
            default => null,
        };
    }

    private function handleOrderPaid(Payment $payment): void
    {
        $order = \App\Models\Order::where('id', $payment->business_id)->orWhere('payment_order_no', $payment->order_no)->first();
        if (!$order) return;
        if (in_array($order->status, ['paid','shipped','completed','cancelled'])) return;
        $order->update(['status'=>'paid','paid_at'=>now(),'payment_channel'=>$payment->channel]);
        // 销量+1，库存已在下单时扣减
        foreach ($order->items as $item) {
            \App\Models\Product::where('id', $item->product_id)->increment('sales', $item->quantity);
        }
    }

    private function activateSubscription(Payment $payment): void
    {
        $sub = Subscription::where('id',$payment->business_id)->lockForUpdate()->first();
        if (!$sub) return;
        if ($sub->status === 'active') return;

        $plan = MembershipPlan::findOrFail($sub->membership_plan_id);
        $now = $payment->paid_at ?? now();
        $sub->update([
            'status'=>'active',
            'starts_at'=>$now,
            'ends_at'=>$plan->calcEndsAt($now),
            'trial_ends_at'=>$plan->trial_days ? $now->copy()->addDays($plan->trial_days) : null,
            'paid_amount'=>$payment->amount,
        ]);
        // 初始化 feature quota
        $plan->load('features');
        foreach ($plan->features as $feat) {
            \App\Models\SubscriptionFeatureUsage::firstOrCreate(
                ['subscription_id'=>$sub->id,'feature_id'=>$feat->id],
                ['used'=>0,'quota'=>$feat->pivot->quota]
            );
        }
    }

    private function handleWalletRecharge(Payment $payment): void
    {
        // 传入支付单号建立流水关联，直接用返回的流水回填 business_id（钱包ID）
        $tx = $this->membershipService->walletChange($payment->tenant_id,$payment->user_id,(float)$payment->amount,'recharge',$payment->subject,$payment->order_no);
        $payment->update(['business_id'=>$tx->wallet_id]);
    }

    public function handleCallback(string $channel, array $payload, ?string $signature = null, ?string $rawBody = null): Payment
    {
        $gateway = PaymentGatewayFactory::make($channel);
        if (!$gateway->verifyWebhook($payload, $signature, $rawBody)) {
            throw new \RuntimeException('回调验签失败');
        }
        // 兼容多种网关：顶层字段 / Stripe 事件包一层 data.object（metadata.order_no 或 client_reference_id）
        $obj = $payload['data']['object'] ?? [];
        $orderNo = $payload['order_no'] ?? $payload['out_trade_no'] ?? $payload['client_reference_id']
            ?? ($obj['metadata']['order_no'] ?? null) ?? ($obj['client_reference_id'] ?? null);
        if (!$orderNo) throw new \RuntimeException('回调缺少 order_no');
        $payment = Payment::where('order_no',$orderNo)->firstOrFail();
        if ($payment->isPaid()) return $payment;
        // Stripe：只处理"支付成功"类事件，其余事件（退款/争议等）静默确认
        $type = $payload['type'] ?? null;
        if ($channel === 'stripe' && $type && !in_array($type, ['checkout.session.completed','payment_intent.succeeded'])) {
            return $payment;
        }

        $payment->update(['callback_data'=>$payload]);
        return $this->markPaid($payment);
    }

    public function query(Payment $payment): array
    {
        $gateway = PaymentGatewayFactory::make($payment->channel);
        $result = $gateway->query($payment);
        // 主动对账兜底：网关侧已支付但本单仍 pending（如 webhook 不可达时），以查询结果核销
        if ($payment->isPending() && in_array($result['status'] ?? null, ['paid','succeeded'], true)) {
            try { $this->markPaid($payment); } catch (\Throwable $e) { /* 过期/已取消等，忽略 */ }
        }
        return $result;
    }

    public function refund(Payment $payment, ?float $amount = null): Payment
    {
        if (!$payment->isPaid()) throw new \RuntimeException('仅已支付订单可退款');
        $gateway = PaymentGatewayFactory::make($payment->channel);
        $result = $gateway->refund($payment, $amount);
        if (!($result['success'] ?? false)) throw new \RuntimeException('退款失败: '.($result['error'] ?? 'unknown'));

        $payment->update(['status'=>$amount && $amount < (float)$payment->amount ? 'partial_refunded' : 'refunded','refunded_at'=>now()]);
        // 若是订阅退款则取消订阅
        if ($payment->business_type==='subscription' && $payment->business_id) {
            Subscription::where('id',$payment->business_id)->update(['status'=>'cancelled','cancelled_at'=>now()]);
        }
        return $payment;
    }

    public function cancel(Payment $payment): Payment
    {
        if (!$payment->isPending()) throw new \RuntimeException('仅待支付可取消');
        return DB::transaction(function () use ($payment) {
            $fresh = Payment::where('id',$payment->id)->lockForUpdate()->first();
            if (!$fresh || !$fresh->isPending()) return $payment; // 幂等：并发下已被处理
            $fresh->update(['status'=>'cancelled']);
            match($fresh->business_type) {
                'subscription' => Subscription::where('id',$fresh->business_id)->where('status','pending')->update(['status'=>'cancelled','cancelled_at'=>now()]),
                'order' => $this->cancelOrderRestoreStock($fresh),
                default => null,
            };
            return $fresh;
        });
    }

    // 取消未支付订单并回补库存（销量在支付成功时才累计，无需回滚）
    private function cancelOrderRestoreStock(Payment $payment): void
    {
        $order = \App\Models\Order::where('id',$payment->business_id)->orWhere('payment_order_no',$payment->order_no)->first();
        if (!$order || $order->status !== 'pending') return;
        $order->update(['status'=>'cancelled']);
        foreach ($order->items as $item) {
            \App\Models\Product::where('id', $item->product_id)->increment('stock', $item->quantity);
        }
        // 退回下单时抵现扣减的积分（adjust 类型，不享受等级倍率）
        if ((int) $order->points_used > 0) {
            $this->membershipService->addPoints($order->tenant_id, $order->user_id, (int) $order->points_used, 'adjust', "订单取消退回积分 {$order->order_no}", 'order', $order->id);
        }
    }

    private function defaultSubject(string $type, array $opts): string
    {
        return match($type) {
            'subscription' => $opts['subject'] ?? '会员订阅',
            'wallet_recharge' => '钱包充值',
            'order' => $opts['subject'] ?? '商城订单',
            default => '支付',
        };
    }

    private function calcCouponDiscount(Coupon $coupon, float $amount, array $opts): float
    {
        if (!$coupon->is_active) throw new \RuntimeException('优惠券不可用');
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) throw new \RuntimeException('优惠券未到可用时间');
        if ($coupon->ends_at && $coupon->ends_at->isPast()) throw new \RuntimeException('优惠券已过期');
        if ($coupon->min_amount > 0 && $amount < (float)$coupon->min_amount) throw new \RuntimeException('未达到满减门槛');
        if ($coupon->type === 'cash') return min((float)$coupon->value, $amount);
        if ($coupon->type === 'discount') return round($amount * (1 - (float)$coupon->value), 2);
        return 0;
    }
}
