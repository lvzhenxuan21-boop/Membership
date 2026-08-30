<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class AlipayGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        if ($this->isMockMode()) {
            Log::warning('[AlipayGateway] 未配置或未安装 yansongda/pay，降级 Mock');
            return (new MockGateway)->pay($payment);
        }
        return [
            'pay_url' => url("/api/v1/payment/{$payment->order_no}/mock-pay"),
            'channel' => 'alipay',
            'mock' => true,
            'message' => '支付宝占位：请配置 ALIPAY_* 并安装 yansongda/pay 后替换此处',
        ];
    }
    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool { return true; }
    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'alipay']; }
    public function refund(Payment $payment, ?float $amount = null): array { return ['success'=>true, 'channel'=>'alipay']; }
    // 未配置 ALIPAY_APP_ID 或未安装 yansongda/pay 时视为 Mock 模式（与 pay() 降级条件保持一致）
    public function isMockMode(): bool
    {
        return empty(config('payments.channels.alipay.app_id')) || !class_exists(\Yansongda\Pay\Pay::class);
    }
}
