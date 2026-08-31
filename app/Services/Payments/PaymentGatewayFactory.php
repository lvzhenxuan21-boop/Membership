<?php

namespace App\Services\Payments;

class PaymentGatewayFactory
{
    public static function make(string $channel): GatewayInterface
    {
        return match($channel) {
            'wechat' => new WechatGateway(),
            'alipay' => new AlipayGateway(),
            'stripe' => new StripeCashierGateway(),
            'wallet' => new WalletGateway(),
            'manual' => new ManualGateway(),
            'mock' => new MockGateway(),
            default => new MockGateway(),
        };
    }

    public static function channels(): array
    {
        return ['mock','wechat','alipay','stripe','wallet','manual'];
    }

    /**
     * Mock 模式准入：PAYMENT_MOCK_ENABLED 显式开关优先；
     * 未设置时仅非生产环境放行（生产默认禁用 mock 渠道与 mock 回调，防止伪造支付）。
     */
    public static function mockAllowed(): bool
    {
        $flag = config('payments.mock_enabled');
        if ($flag !== null && $flag !== '') return (bool) $flag;
        return !app()->environment('production');
    }
}
