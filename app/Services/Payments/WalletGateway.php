<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        // 余额支付：直接扣减钱包，事务内完成
        return DB::transaction(function () use ($payment) {
            $wallet = Wallet::where('tenant_id', $payment->tenant_id)
                ->where('user_id', $payment->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet || bccomp((string)$wallet->balance, (string)$payment->amount, 2) < 0) {
                throw new \RuntimeException('钱包余额不足，请先充值');
            }

            $newBalance = bcsub((string)$wallet->balance, (string)$payment->amount, 2);
            $wallet->update([
                'balance' => $newBalance,
                'total_consumed' => bcadd((string)$wallet->total_consumed, (string)$payment->amount, 2),
            ]);

            // 同步冗余到 profile
            \App\Models\MemberProfile::where('tenant_id', $payment->tenant_id)
                ->where('user_id', $payment->user_id)
                ->update(['balance' => $newBalance]);

            \App\Models\WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'consume',
                'amount' => -abs((float)$payment->amount),
                'balance_after' => $newBalance,
                'order_no' => $payment->order_no,
                'description' => $payment->subject.' 余额支付',
                'meta' => ['payment_id' => $payment->id],
            ]);

            // 钱包支付视为即时成功，外部 PaymentService 会再标记 paid
            return [
                'pay_url' => null,
                'channel' => 'wallet',
                'wallet_paid' => true,
                'balance_after' => $newBalance,
            ];
        });
    }

    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool { return true; }
    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'wallet']; }
    public function refund(Payment $payment, ?float $amount = null): array
    {
        $refund = $amount ?? (float)$payment->amount;
        // 退款回余额，并同步回调减累计消费统计（支付时记入了 total_consumed，退款不冲减会虚高）
        $wallet = Wallet::where('tenant_id', $payment->tenant_id)->where('user_id', $payment->user_id)->lockForUpdate()->first();
        if ($wallet) {
            $newBalance = bcadd((string)$wallet->balance, (string)$refund, 2);
            $newConsumed = bcsub((string)$wallet->total_consumed, (string)$refund, 2);
            if (bccomp($newConsumed, '0', 2) < 0) $newConsumed = '0.00';
            $wallet->update(['balance'=>$newBalance, 'total_consumed'=>$newConsumed]);
            \App\Models\MemberProfile::where('tenant_id',$payment->tenant_id)->where('user_id',$payment->user_id)->update(['balance'=>$newBalance]);
            \App\Models\WalletTransaction::create([
                'wallet_id'=>$wallet->id,'type'=>'refund','amount'=>$refund,'balance_after'=>$newBalance,
                'order_no'=>$payment->order_no,'description'=>'退款 '.$payment->subject,
            ]);
        }
        return ['success'=>true, 'refund_amount'=>$refund];
    }
    // 余额在创建支付单时即时扣款，不存在待支付的 mock 环节
    public function isMockMode(): bool { return false; }
}
