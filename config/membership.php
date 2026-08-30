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
];
