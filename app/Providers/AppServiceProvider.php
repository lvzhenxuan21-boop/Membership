<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 兼顾方案：本项目自有 subscriptions 表与 Cashier 订阅表重名
        // Cashier 已改为 publishesMigrations，不会自动加载迁移，无需 ignore
        // 保留 Billable 的 stripe_id 能力，支付走 Payment + stripe-php/Cashier 混合
    }
}
