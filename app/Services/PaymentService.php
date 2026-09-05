<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // 生产环境禁用 mock 渠道（含未配置密钥而降级 Mock 的真实渠道），防止伪造支付
        if ($channel === 'mock' && !PaymentGatewayFactory::mockAllowed()) {
            throw new \RuntimeException('生产环境未启用 mock 支付渠道，请配置真实支付渠道（PAYMENT_DEFAULT_CHANNEL）');
        }
        if (!in_array($channel, ['mock','wallet'], true)) {
            $gateway = PaymentGatewayFactory::make($channel);
            if ($gateway->isMockMode() && !PaymentGatewayFactory::mockAllowed()) {
                throw new \RuntimeException("渠道 {$channel} 未配置真实密钥，生产环境禁止降级 Mock");
            }
        }
        // 余额渠道防护：钱包充值不能用自己的余额付（扣了又充，净额为零还会污染账务）；开关未开时拒绝
        if ($businessType === 'wallet_recharge' && $channel === 'wallet') {
            throw new \InvalidArgumentException('钱包充值不能使用余额支付渠道');
        }
        if ($channel === 'wallet' && !config('payments.wallet_enabled', true)) {
            throw new \InvalidArgumentException('余额支付渠道未开启');
        }

        // 订阅类型金额一律以套餐价为准，忽略调用方传入金额（防改价），后续优惠券按套餐价计算
        $plan = null;
        if ($businessType === 'subscription' && !empty($opts['plan_id'])) {
            $plan = MembershipPlan::where('tenant_id',$tenantId)->findOrFail($opts['plan_id']);
            $amount = (float)$plan->price;
            $opts['subject'] = $plan->name;
        }

        // 优惠券抵扣（校验与名额占用移入下方事务，防止并发超发）
        $couponId = $opts['coupon_id'] ?? null;
        $discount = 0;
        $originalAmount = $opts['original_amount'] ?? $amount;

        return DB::transaction(function () use ($tenantId,$userId,$businessType,$amount,$originalAmount,$discount,$couponId,$channel,$opts,$plan) {
            // 优惠券：锁定行校验配额并占用一个名额（取消支付时释放，支付成功不重复计数）
            if ($couponId) {
                $coupon = Coupon::where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($couponId);
                $discount = $this->calcCouponDiscount($coupon, $amount);
                if ($coupon->total_quota > 0 && $coupon->used_count >= $coupon->total_quota) {
                    throw new \RuntimeException('优惠券已被领完');
                }
                if ($coupon->per_user_limit > 0) {
                    // pending 也占个人名额防并发绕过；已退款不返还名额（券语义：用了就算）
                    $mine = Payment::where('coupon_id',$coupon->id)->where('user_id',$userId)
                        ->whereIn('status',['pending','paid','partial_refunded','refunded'])->count();
                    if ($mine >= $coupon->per_user_limit) {
                        throw new \RuntimeException("每人限用 {$coupon->per_user_limit} 次，已达使用上限");
                    }
                }
                $coupon->increment('used_count');
                $amount = max(0, round($amount - $discount, 2));
            }

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

            // 0元单跳过网关直接成功（不强制 mock 渠道）
            if ((float)$payment->amount === 0.0) {
                return $this->markPaid($payment)->fresh();
            }

            // 余额扣款是本地库操作：留在事务内，余额不足时随整单原子回滚，不产生死单
            if ($channel === 'wallet') {
                $payData = PaymentGatewayFactory::make('wallet')->pay($payment);
                $payment->update(['channel_data'=>$payData]);
                return $this->markPaid($payment, $payData['balance_after'] ?? null)->fresh();
            }

            return $payment->fresh(); // pending，网关调用在事务提交后进行
        });

        // 外部网关（Stripe/微信/支付宝真实 HTTP 调用）在事务提交后执行，
        // 避免网关等待期间长时间持有优惠券名额等行锁；失败不抛出，支付单保持 pending 可重试
        if ($payment->status === 'pending') {
            $gateway = PaymentGatewayFactory::make($channel);
            try {
                $payData = $gateway->pay($payment);
                $payment->update(['pay_url'=>$payData['pay_url'] ?? null, 'channel_data'=>$payData]);
            } catch (\Throwable $e) {
                $payment->update(['channel_data'=>['error'=>$e->getMessage()]]);
            }
        }

        return $payment->fresh();
    }

    public function markPaid(Payment $payment, $extra = null, bool $allowLate = false): Payment
    {
        if ($payment->isPaid()) return $payment;
        if (!$payment->isPending()) throw new \RuntimeException('仅待支付订单可标记支付');

        return DB::transaction(function () use ($payment, $extra, $allowLate) {
            $payment = Payment::where('id',$payment->id)->lockForUpdate()->first();
            if ($payment->isPaid()) return $payment;
            $late = $payment->isExpired();
            if ($late && !$allowLate) throw new \RuntimeException('支付已超时');

            // 合并保留既有 callback_data（此前会整体覆盖，丢失网关原始报文）
            $callbackData = array_merge($payment->callback_data ?? [], ['manual_mark'=>true, 'extra'=>$extra]);
            if ($late) {
                // 网关已确认收款但本地已过有效期：补核销并打晚到标记，钱不能吞
                $callbackData['late_mark'] = true;
                Log::warning('[PaymentService] 晚到款核销：支付单已过有效期但网关确认收款', [
                    'order_no'=>$payment->order_no, 'channel'=>$payment->channel,
                ]);
            }
            $payment->update(['status'=>'paid','paid_at'=>now(),'callback_data'=>$callbackData]);

            // 触发业务
            $this->handleBusinessSuccess($payment);

            // 优惠券核销：名额已在创建支付单时占用，此处仅消费用户领取的券实例（如后台发券）
            if ($payment->coupon_id) {
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
        // 支付宝/微信：回调携带的交易状态非成功时不核销
        if ($channel === 'alipay' && isset($payload['trade_status'])
            && !in_array($payload['trade_status'], ['TRADE_SUCCESS','TRADE_FINISHED'], true)) {
            throw new \RuntimeException('支付宝回调交易状态非成功: '.$payload['trade_status']);
        }
        if ($channel === 'wechat' && isset($payload['result_code']) && $payload['result_code'] !== 'SUCCESS') {
            throw new \RuntimeException('微信回调交易状态非成功: '.$payload['result_code']);
        }
        // Stripe：只处理"支付成功"类事件，其余事件（退款/争议等）静默确认
        $type = $payload['type'] ?? null;
        if ($channel === 'stripe' && $type && !in_array($type, ['checkout.session.completed','payment_intent.succeeded'])) {
            return $payment;
        }

        // 网关已确认收款：
        if (!$payment->isPending()) {
            // 已取消/失败的单不能自动复活（库存/优惠券名额可能已释放），落库留痕并告警人工核实
            $payment->update(['callback_data' => array_merge($payment->callback_data ?? [], [
                'late_payment_unresolved' => true, 'gateway_payload' => $payload,
            ])]);
            Log::critical('[PaymentService] 网关回调确认收款但支付单状态为 '.$payment->status.'，需人工核实', [
                'order_no'=>$orderNo, 'channel'=>$channel,
            ]);
            return $payment;
        }

        $payment->update(['callback_data'=>$payload]);
        // 网关确认收款即核销：即使已过有效期（allowLate）也补账，避免"钱收了、单没核"的资金悬空
        return $this->markPaid($payment, null, true);
    }

    public function query(Payment $payment): array
    {
        $gateway = PaymentGatewayFactory::make($payment->channel);
        $result = $gateway->query($payment);
        $gatewayPaid = in_array($result['status'] ?? null, ['paid','succeeded'], true);
        // 主动对账兜底：网关侧已支付但本单仍 pending（如 webhook 不可达时），以查询结果核销
        if ($payment->isPending() && $gatewayPaid) {
            try { $this->markPaid($payment, null, true); } catch (\Throwable $e) {
                Log::warning('[PaymentService] 对账核销失败: '.$e->getMessage(), ['order_no'=>$payment->order_no]);
            }
        } elseif (!$payment->isPending() && $gatewayPaid) {
            // 已取消/失败但网关侧已支付：留痕告警，人工处理（不能自动复活）
            $payment->update(['callback_data' => array_merge($payment->callback_data ?? [], [
                'late_payment_unresolved' => true, 'query_status' => $result['status'] ?? null,
            ])]);
            Log::critical('[PaymentService] 对账发现网关已收款但支付单状态为 '.$payment->status.'，需人工核实', [
                'order_no'=>$payment->order_no,
            ]);
        }
        return $result;
    }

    public function refund(Payment $payment, ?float $amount = null): Payment
    {
        // 事务 + 行锁：防并发双退；部分退款累计 refunded_amount，余款可继续退
        return DB::transaction(function () use ($payment, $amount) {
            $fresh = Payment::where('id', $payment->id)->lockForUpdate()->first();
            if (!$fresh || !in_array($fresh->status, ['paid','partial_refunded'], true)) {
                throw new \RuntimeException('仅已支付订单可退款');
            }
            $remaining = round((float)$fresh->amount - (float)$fresh->refunded_amount, 2);
            if ($remaining <= 0) throw new \RuntimeException('订单已全额退款');

            $refundAmount = $amount !== null ? min(round((float)$amount, 2), $remaining) : $remaining;
            $gateway = PaymentGatewayFactory::make($fresh->channel);
            $result = $gateway->refund($fresh, $refundAmount === (float)$fresh->amount ? null : $refundAmount);
            if (!($result['success'] ?? false)) throw new \RuntimeException('退款失败: '.($result['error'] ?? 'unknown'));

            $refundedTotal = round((float)$fresh->refunded_amount + $refundAmount, 2);
            $fullyRefunded = bccomp((string)$refundedTotal, (string)$fresh->amount, 2) >= 0;
            $fresh->update([
                'status' => $fullyRefunded ? 'refunded' : 'partial_refunded',
                'refunded_amount' => $refundedTotal,
                'refunded_at' => now(),
            ]);
            // 订阅仅在全额退款时取消（部分退款保留会员权益）
            if ($fullyRefunded && $fresh->business_type==='subscription' && $fresh->business_id) {
                Subscription::where('id',$fresh->business_id)->where('status','active')->update(['status'=>'cancelled','cancelled_at'=>now()]);
            }
            return $fresh;
        });
    }

    public function cancel(Payment $payment): Payment
    {
        if (!$payment->isPending()) throw new \RuntimeException('仅待支付可取消');
        return DB::transaction(function () use ($payment) {
            $fresh = Payment::where('id',$payment->id)->lockForUpdate()->first();
            if (!$fresh || !$fresh->isPending()) return $payment; // 幂等：并发下已被处理
            $fresh->update(['status'=>'cancelled']);
            // 释放创建支付单时占用的优惠券名额
            if ($fresh->coupon_id) {
                Coupon::where('id',$fresh->coupon_id)->where('used_count','>',0)->decrement('used_count');
            }
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

    private function calcCouponDiscount(Coupon $coupon, float $amount): float
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
