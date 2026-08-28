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
}
