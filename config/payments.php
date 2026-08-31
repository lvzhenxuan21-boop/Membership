<?php

return [
    // 默认支付渠道（演示用 mock，生产改 wechat/alipay/stripe）
    'default_channel' => env('PAYMENT_DEFAULT_CHANNEL', 'mock'),

    // Mock 渠道总开关：null=自动（非生产放行，生产禁用）；显式 true/false 优先。
    // 生产环境务必保持 unset/false，否则 mock-pay 可伪造支付成功。
    'mock_enabled' => env('PAYMENT_MOCK_ENABLED'),

    // 支付超时（分钟）
    'expire_minutes' => env('PAYMENT_EXPIRE_MINUTES', 30),

    // 是否开启钱包余额支付
    'wallet_enabled' => env('PAYMENT_WALLET_ENABLED', true),

    // 各渠道开关与密钥占位（买家填真实密钥即接通）
    'channels' => [
        'mock' => [
            'enabled' => true,
            'name' => '模拟支付（演示）',
        ],
        'wechat' => [
            'enabled' => env('WECHAT_PAY_ENABLED', false),
            'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
            'mch_key' => env('WECHAT_PAY_MCH_KEY', ''),
            'app_id' => env('WECHAT_PAY_APP_ID', ''),
            // 买家在此接入 yansongda/pay 等 SDK
        ],
        'alipay' => [
            'enabled' => env('ALIPAY_ENABLED', false),
            'app_id' => env('ALIPAY_APP_ID', ''),
            'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
            'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
        ],
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'secret' => env('STRIPE_SECRET', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],
        'wallet' => [
            'enabled' => env('PAYMENT_WALLET_ENABLED', true),
            'name' => '余额支付',
        ],
        'manual' => [
            'enabled' => true,
            'name' => '线下/人工核销',
        ],
    ],

    // 回调地址（自动拼接 APP_URL）
    'callback_url' => env('PAYMENT_CALLBACK_URL', null),
    'notify_url' => env('PAYMENT_NOTIFY_URL', null),

    // 货币
    'currency' => env('PAYMENT_CURRENCY', 'CNY'),
];
