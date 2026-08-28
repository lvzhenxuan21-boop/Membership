# 可售版 Laravel 会员系统 - D:\php_Project

> 基于 GitHub 高星项目优点聚合，Laravel 13 + PHP 8.3 + Spatie Permission，支持多租户SaaS售卖

## 1. GitHub 调研汇总 (laravel membership system)

直接搜 `laravel membership` 的纯会员包星数极低 (jobmetric 7★, vimatech 4★)，不具备参考价值。实际高星项目分布在 **订阅/权限/电商/健身** 四个关联领域，本系统已聚合其核心优点：

| 项目 | Stars | 核心优点 | 已吸收到本系统 |
|------|-------|----------|---------------|
| **spatie/laravel-permission** | 12911★ | RBAC角色权限、缓存、Guard、Blade指令 | ✅ 已安装 `spatie/laravel-permission:8.3`，User已use HasRoles，Seeder预置 `tenant_admin/staff` + 7个权限 |
| **santigarcor/laratrust** | 2274★ | 多用户模型、Team团队(多租户) | ✅ `tenants`表 + `member_profiles.tenant_id` 多租户隔离，支持同一User跨商户 |
| **lucasdotvin/laravel-soulbscription** | 707★ | Plan/Feature/Subscription/Ticket 配额消费模型，最优雅的订阅抽象 | ✅ `membership_plans` + `features` + `feature_plan(quota)` + `subscription_feature_usages` + `MembershipService::consumeFeature()` |
| **hasinhayder/tyro** | 678★ | Sanctum、40+ artisan、封禁工作流 | 思想吸收：预留 `member_profiles.status=frozen` + Sanctum可直接接入 |
| **johndavedecano/laragym** | 354★ | Gym完整业务：Packages/BillingCycles/多分支/考勤/审计日志/PWA | ✅ `branches`多分支 + `billing_cycle` 7种计费 + `check_ins`考勤 + `activity_logs` |
| **laravelcm/laravel-subscriptions** | 243★ | SaaS Plans/Subscriptions 管理 | ✅ `MembershipPlan::calcEndsAt()` 统一到期计算 |
| **Foysal50x/tashil** | - | Redis缓存、不可变事件溯源、原子化usage | ✅ `point_ledgers.balance_after` + `wallets` 事务锁 `lockForUpdate()` + `MembershipService` 原子化 |
| **liberu/ecommerce-laravel** | 180★ | Laravel13/Filament5/Livewire4、Stripe工厂、模块化 | 架构吸收：预留 Filament 接入点，`payment_method` 工厂化 |

## 2. 可售版架构 (聚合后)

```
Tenant (商户/租户) 1--N Branch (门店)
  | 1--N MembershipLevel (等级: 普通/银/金/钻石 + 折扣/积分倍率)
  | 1--N Feature (权益: 免费配送/券包/折扣)
  | 1--N MembershipPlan (套餐: 月卡/年卡/终身) N--N Feature (quota)
  | 1--N Coupon / CouponUser
User 1--N MemberProfile (tenant隔离, member_no, points/growth/balance)
  | 1--N Subscription (订单号, starts/ends_at, trial) 1--N SubscriptionFeatureUsage (used/quota)
  | 1--N PointLedger (不可变账本)
  | 1--1 Wallet + N WalletTransaction (储值)
  | N CheckIn / ActivityLog
Spatie: roles/permissions/model_has_roles
```

**商业模式覆盖：**
- 付费会员 (88VIP/PLUS) -> `membership_plans` + `subscriptions`
- 等级+积分 -> `membership_levels` + `point_ledgers` + 自动 `recalcLevel()`
- 储值 -> `wallets`
- 券 -> `coupons`
- 多店连锁 -> `branches`

## 3. 快速开始

```bash
# 已在 D:\php_Project 初始化完成，Laravel 13.26.1
composer install
php artisan migrate:fresh --seed
# 演示账号: demo@member.com / 123456 (tenant_admin)

# 测试API
php artisan serve
curl http://127.0.0.1:8000/api/v1/membership/plans?tenant_id=1
curl http://127.0.0.1:8000/api/v1/membership/profile/1?tenant_id=1
```

## 4. 核心服务 App\Services\MembershipService

```php
$svc->subscribe($tenantId,$userId,$planId, ['payment_method'=>'wechat']); // 原子创建订阅+feature_usages
$svc->addPoints($tenantId,$userId, 100, 'earn', '消费赠送'); // 锁行 + 自动升级
$svc->walletChange($tenantId,$userId, 199.00, 'recharge', '充值'); // 余额 + 事务
$svc->consumeFeature($subscriptionId, 'FREE_SHIPPING', 1); // 配额扣减
```

所有写操作均 `DB::transaction + lockForUpdate`，避免并发超扣 (Tashil思想)。

## 5. API (routes/api.php, prefix api/v1)
- GET  /membership/plans?tenant_id=1
- GET  /membership/levels?tenant_id=1
- GET  /membership/profile/{userId}?tenant_id=1
- POST /membership/subscribe {tenant_id,user_id,plan_id}
- POST /membership/points/add {tenant_id,user_id,points,type}
- POST /membership/wallet/recharge {tenant_id,user_id,amount}
- POST /membership/feature/consume {subscription_id,feature_code}

## 6. 下一步可售化增强 (建议)
1. 接 Filament 5 后台 (参考 Liberu): `composer require filament/filament` 后为 Tenant/Plan/Member 生成 Resources
2. 接 Laravel Cashier + Stripe/微信支付工厂
3. 加 Sanctum API Token + Tyro封禁逻辑
4. 加 Tenant 独立域名/开箱即用安装向导 (参考 laravel-creem-demo 零配置)

## 7. 目录
- database/migrations/2026_08_24_100000_create_membership_core_tables.php (14张表)
- app/Models/* (Tenant, Branch, MembershipLevel, Feature, MembershipPlan, MemberProfile, Subscription, ...)
- app/Services/MembershipService.php
- app/Http/Controllers/Api/MembershipController.php
- database/seeders/MembershipSeeder.php

已验证: migrate:fresh --seed ✅, 订阅/积分/储值/权益消费 全流程 ✅
