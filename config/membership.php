<?php

return [
    // 积分抵现：多少积分抵 1 元
    'points_per_yuan' => env('MEMBERSHIP_POINTS_PER_YUAN', 100),

    // 商城下单是否允许积分抵现
    'points_redeem_enabled' => env('MEMBERSHIP_POINTS_REDEEM', true),

    // 订阅到期前 N 天发送提醒
    'subscription_remind_days' => env('MEMBERSHIP_SUBSCRIPTION_REMIND_DAYS', 3),

    // 保级周期（月）：到期后按周期内成长值重算等级，不达标降级
    'relevel_period_months' => env('MEMBERSHIP_RELEVEL_MONTHS', 12),

    // 线上演示店铺地址（首页「先逛逛示例店铺」入口）；留空则用本地 /shop/{demo}
    'demo_store_url' => env('MEMBERSHIP_DEMO_STORE_URL'),

    // 同一 IP 每日最多签到账号次数（防脚本批量刷）
    'checkin_ip_daily_limit' => env('MEMBERSHIP_CHECKIN_IP_LIMIT', 20),

    // 自助开店是否自动激活：生产建议 false（开店后置 pending，由平台在后台审核激活）
    'tenant_auto_activate' => env('MEMBERSHIP_TENANT_AUTO_ACTIVATE', true),

    // 裸域访问 /pricing /me /check-in 等页面时的兜底商户 slug；为空则这些页面要求从商户站点进入
    'default_tenant_slug' => env('MEMBERSHIP_DEFAULT_TENANT_SLUG', 'demo'),

    // 单用户最多同时存在的未支付订单数（pending 订单锁库存，防脚本刷单占压库存；0=不限制）
    'max_pending_orders' => env('MEMBERSHIP_MAX_PENDING_ORDERS', 5),
];
