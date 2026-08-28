<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface GatewayInterface
{
    // 创建支付，返回含 pay_url / qr_code / client_secret 等给前端
    public function pay(Payment $payment): array;

    // 验证回调签名
    public function verifyWebhook(array $payload, ?string $signature = null): bool;

    // 查询网关侧状态
    public function query(Payment $payment): array;

    // 退款
    public function refund(Payment $payment, ?float $amount = null): array;
}
