<?php

namespace App\Services\Payments;

use App\Models\Payment;

class ManualGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        return [
            'pay_url' => null,
            'channel' => 'manual',
            'message' => '线下支付：请联系商户完成转账，管理员在后台标记为已支付',
        ];
    }
    public function verifyWebhook(array $payload, ?string $signature = null): bool { return true; }
    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'manual']; }
    public function refund(Payment $payment, ?float $amount = null): array { return ['success'=>true, 'channel'=>'manual']; }
}
