<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class MockGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        // 演示用：直接生成一个模拟支付链接，前端可直接调用 /payments/{order_no}/mock-pay 模拟成功
        $payUrl = url("/api/v1/payment/{$payment->order_no}/mock-pay");
        Log::info("[MockGateway] create pay", ['order_no'=>$payment->order_no, 'amount'=>$payment->amount]);

        return [
            'pay_url' => $payUrl,
            'qr_code' => $payUrl,
            'channel' => 'mock',
            'mock' => true,
            'message' => '演示支付：访问 pay_url 或调用 mock-pay 接口即视为支付成功',
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool
    {
        return true;
    }

    public function query(Payment $payment): array
    {
        return ['status' => $payment->status, 'mock' => true];
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        return ['success' => true, 'mock' => true, 'refund_amount' => $amount ?? $payment->amount];
    }

    public function isMockMode(): bool { return true; }
}
