<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface GatewayInterface
{
    // 创建支付，返回含 pay_url / qr_code / client_secret 等给前端
    public function pay(Payment $payment): array;

    // 验证回调签名 ($rawBody: 原始请求体，验签必须用原文而非重新编码的数组)
    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool;

    // 查询网关侧状态
    public function query(Payment $payment): array;

    // 退款
    public function refund(Payment $payment, ?float $amount = null): array;

    // 当前是否运行在 Mock 模式（未配置密钥时的演示降级）；mock-pay 仅允许对 Mock 模式支付单生效
    public function isMockMode(): bool;
}
