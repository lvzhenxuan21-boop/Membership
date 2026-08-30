<?php

namespace App\Services;

use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\PointLedger;
use App\Models\Subscription;
use App\Models\SubscriptionFeatureUsage;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 可售版会员核心服务 - 聚合 GitHub 高星项目优点
 * - Soulbscription: plan/feature/subscription + quota
 * - Tashil: 原子化积分账本 + 事务
 * - Laragym: 多分支/考勤/套餐
 * - Spatie Permission: 角色权限已解耦到User
 */
class MembershipService
{
    // 订阅购买 - 原子化创建 subscription + feature_usages + 订单号
    public function subscribe(int $tenantId, int $userId, int $planId, array $opts = []): Subscription
    {
        $plan = MembershipPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        return DB::transaction(function () use ($tenantId, $userId, $plan, $opts) {
            $now = now();
            $sub = Subscription::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'membership_plan_id' => $plan->id,
                'order_no' => 'SUB'.date('YmdHis').strtoupper(Str::random(6)),
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $plan->calcEndsAt($now),
                'trial_ends_at' => $plan->trial_days ? $now->copy()->addDays($plan->trial_days) : null,
                'payment_method' => $opts['payment_method'] ?? 'manual',
                'paid_amount' => $opts['paid_amount'] ?? $plan->price,
                'meta' => $opts['meta'] ?? null,
            ]);
            // 初始化 feature quota (Soulbscription模式)
            $plan->load('features');
            foreach ($plan->features as $feat) {
                SubscriptionFeatureUsage::create([
                    'subscription_id' => $sub->id,
                    'feature_id' => $feat->id,
                    'used' => 0,
                    'quota' => $feat->pivot->quota,
                ]);
            }
            return $sub;
        });
    }

    // 积分 Earn/Spend - 原子化账本 (不可直接改points字段)
    public function addPoints(int $tenantId, int $userId, int $points, string $type, string $desc, ?string $sourceType=null, ?int $sourceId=null): PointLedger
    {
        return DB::transaction(function () use ($tenantId,$userId,$points,$type,$desc,$sourceType,$sourceId) {
            $profile = MemberProfile::where('tenant_id',$tenantId)->where('user_id',$userId)->lockForUpdate()->firstOrFail();

            // 等级积分倍率：仅对入账(earn)生效，spend/adjust 不放大
            $delta = $points;
            if ($type === 'earn' && $points > 0) {
                $multiplier = (int) ($profile->level->points_multiplier ?? 1);
                if ($multiplier > 1) {
                    $delta = $points * $multiplier;
                    $desc .= "（{$profile->level->name} 积分倍率×{$multiplier}）";
                }
            }

            $newBalance = $profile->points + $delta;
            if ($newBalance < 0) throw new \RuntimeException('积分不足');
            $profile->update(['points'=>$newBalance, 'growth'=> $profile->growth + max(0,$delta)]);
            $profile->recalcLevel();
            return PointLedger::create([
                'tenant_id'=>$tenantId,'user_id'=>$userId,'type'=>$type,'points'=>$delta,'balance_after'=>$newBalance,
                'source_type'=>$sourceType,'source_id'=>$sourceId,'description'=>$desc,
            ]);
        });
    }

    // 储值充值/消费 ($orderNo: 关联支付单号，由支付流程充值时传入)
    public function walletChange(int $tenantId, int $userId, float $amount, string $type, string $desc, ?string $orderNo = null): WalletTransaction
    {
        return DB::transaction(function () use ($tenantId,$userId,$amount,$type,$desc,$orderNo) {
            $wallet = Wallet::where('tenant_id',$tenantId)->where('user_id',$userId)->lockForUpdate()->first();
            if (!$wallet) $wallet = Wallet::create(['tenant_id'=>$tenantId,'user_id'=>$userId,'balance'=>0]);
            $newBalance = bcadd((string)$wallet->balance, (string)$amount, 2);
            if (bccomp($newBalance, '0', 2) < 0) throw new \RuntimeException('余额不足');
            $wallet->update([
                'balance'=>$newBalance,
                'total_recharged'=> $amount>0 ? bcadd($wallet->total_recharged,$amount,2) : $wallet->total_recharged,
                'total_consumed'=> $amount<0 ? bcadd($wallet->total_consumed, abs($amount),2) : $wallet->total_consumed,
            ]);
            // 同步冗余到 profile
            MemberProfile::where('tenant_id',$tenantId)->where('user_id',$userId)->update(['balance'=>$newBalance]);
            return WalletTransaction::create([
                'wallet_id'=>$wallet->id,'type'=>$type,'amount'=>$amount,'balance_after'=>$newBalance,
                'order_no'=>$orderNo,'description'=>$desc,
            ]);
        });
    }

    // Feature 消耗 - Soulbscription consume 模式
    public function consumeFeature(int $subscriptionId, string $featureCode, int $amount=1): bool
    {
        return DB::transaction(function () use ($subscriptionId,$featureCode,$amount) {
            $sub = Subscription::with('featureUsages.feature')->findOrFail($subscriptionId);
            if (!$sub->isActive()) throw new \RuntimeException('订阅已过期/未激活');
            $usage = $sub->featureUsages->first(fn($u)=> $u->feature->code===$featureCode);
            if (!$usage) throw new \RuntimeException("Feature {$featureCode} 未包含在此套餐");
            if ($usage->quota!==null && ($usage->used + $amount) > $usage->quota) throw new \RuntimeException('权益次数已耗尽');
            $usage->increment('used', $amount);
            return true;
        });
    }
}
