<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 过期支付单自动取消（回补订单库存/取消 pending 订阅）；生产环境需部署 scheduler（cron: * * * * * php artisan schedule:run）
Schedule::command('payments:cancel-expired')->everyFiveMinutes();

// 订阅到期处理（置 expired）+ 到期前 N 天提醒（邮件 + 审计日志）
Schedule::command('subscriptions:expire')->dailyAt('01:10');

// 会员保级周期重算（周期内成长值不达标降级，并重置基线）
Schedule::command('memberships:relevel')->dailyAt('02:10');
