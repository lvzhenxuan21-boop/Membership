<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class CancelExpiredPayments extends Command
{
    protected $signature = 'payments:cancel-expired';

    protected $description = '取消超时未支付的支付单：回补订单库存、取消 pending 订阅';

    public function handle(PaymentService $svc): int
    {
        $payments = Payment::query()
            ->pending()
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->get();

        if ($payments->isEmpty()) {
            $this->info('没有过期的待支付支付单');
            return self::SUCCESS;
        }

        $done = 0;
        foreach ($payments as $payment) {
            try {
                $svc->cancel($payment);
                $done++;
                $this->line("已取消: {$payment->order_no} ({$payment->business_type})");
            } catch (\Throwable $e) {
                $this->warn("{$payment->order_no} 取消失败: {$e->getMessage()}");
            }
        }
        $this->info("共取消 {$done}/{$payments->count()} 笔过期支付单");
        return self::SUCCESS;
    }
}
