<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\MembershipLevel;
use App\Models\MembershipPlan;
use App\Models\MemberProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PointLedger;
use App\Models\Product;
use App\Models\Scopes\TenantDataScope;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Observers\TenantDataObserver;
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

        // 租户数据隔离：全局作用域管查询（Filament/API/服务层全覆盖），
        // 观察者管写入（强制归属 + 防改挂租户）。Tenant 模型特殊处理为"只看自己这家"。
        $tenantScopedModels = [
            Tenant::class,
            Branch::class,
            MembershipLevel::class,
            Feature::class,
            MembershipPlan::class,
            MemberProfile::class,
            Subscription::class,
            PointLedger::class,
            Wallet::class,
            Coupon::class,
            CheckIn::class,
            ActivityLog::class,
            Shop::class,
            Product::class,
            Order::class,
            Payment::class,
        ];
        foreach ($tenantScopedModels as $model) {
            $model::addGlobalScope(new TenantDataScope());
            $model::observe(TenantDataObserver::class);
        }
    }
}
