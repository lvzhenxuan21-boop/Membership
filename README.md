# Membership Pro — 可售版 Laravel 多租户 SaaS 会员系统

> Laravel 13 + PHP 8.3 + Filament 5 + Spatie Permission + Cashier 16.7 | 16张表全闭环 | 支付已完善 | 开箱即售

![Laravel](https://img.shields.io/badge/Laravel-13.26-red) ![PHP](https://img.shields.io/badge/PHP-8.3-blue) ![Filament](https://img.shields.io/badge/Filament-5.7-orange) ![License](https://img.shields.io/badge/License-Proprietary-yellow)

> 🌐 **在线演示（https://fenshen66.xyz 已上线）**：**商城** <https://fenshen66.xyz/> · **后台** <https://fenshen66.xyz/admin>（`tenant@demo.com / 12345678`）· **健康检查** <https://fenshen66.xyz/api/v1/health> · **API** `https://fenshen66.xyz/api/v1/membership/plans?tenant_id=1`

基于 GitHub 高星项目优点聚合（spatie/laravel-permission 12k★、laratrust、soulbscription、laragym 等），单库支撑 **SaaS 多租户售卖**：商户/门店/等级/权益/套餐/订阅/积分/储值/优惠券/考勤/审计 全闭环。

---

## 1. 功能清单（买家最关心的）

| 模块 | 表 | 卖点 |
|------|----|------|
| **多租户** | `tenants` | 一套代码卖 N 家商户，`tenant_id` 行级隔离 |
| **多门店** | `branches` | 连锁店模式，支持经纬度、电话、状态 |
| **等级体系** | `membership_levels` | 普通/银/金/钻石 + 折扣率/积分倍率/升级门槛 |
| **权益系统** | `features` + `feature_plan` | Soulbscription 模式：quota/boolean/discount 三类，套餐绑定配额 |
| **付费套餐** | `membership_plans` | 月卡/年卡/终身 + 7种计费周期 + 试用期 + 推荐位 |
| **订阅** | `subscriptions` + `subscription_feature_usages` | 订单号、起止时间、Feature 消耗 `consumeFeature()` 原子扣减 |
| **积分** | `point_ledgers` 不可变账本 | `lockForUpdate` + `balance_after` 审计，自动 `recalcLevel()` |
| **储值钱包** | `wallets` + `wallet_transactions` | 充值/消费/冻结，`bcadd` 精度，事务锁 |
| **优惠券** | `coupons` + `coupon_user` | 满减/折扣/包邮，限领/限用/有效期 |
| **考勤签到** | `check_ins` | 门店签到，二维码/手动 |
| **审计日志** | `activity_logs` | 全表操作溯源 |
| **支付** | `payments` + Cashier | 通用支付：mock/钱包/微信/支付宝/Stripe via Cashier，退款/回调原子化 |
| **权限** | Spatie Permission | `super_admin`/`tenant_admin`/`staff`/`member` + 7 权限点 |

**商业模式直接覆盖：** 88VIP式付费会员 / 等级+积分 / 储值 / 券包 / 连锁多店。

---

## 2. 技术栈

- **后端:** Laravel 13.26.1, PHP 8.3, Spatie Permission 8.3, Filament 5.7, Cashier 16.7 + stripe-php 20.3
- **前端:** Vite 8 + Tailwind 4 + Livewire (Filament 自带)
- **数据库:** SQLite 开箱即用（可切 MySQL/PostgreSQL，改 `.env` 即可）
- **核心服务:** `App\Services\MembershipService` 4 原子方法 + `App\Services\PaymentService` 通用支付 + 6 网关工厂（兼顾 Cashier）
- **网关:** `mock` 演示 / `wallet` 余额 / `manual` 线下 / `wechat`/`alipay` (yansongda/pay占位) / `stripe` (Cashier+stripe-php Checkout)

---

## 3. 目录结构

```
app/
  Models/          17 个模型 (+Payment, User Billable)
  Services/MembershipService.php  订阅/积分/储值/权益 核心
  Services/PaymentService.php     通用支付 + 业务钩子
  Services/Payments/              GatewayInterface + 6 网关 (Mock/Wallet/Manual/Wechat/Alipay/StripeCashier)
  Filament/Resources/ 12 个后台资源 (新增 Payments) + AdminPanelProvider
  Http/Controllers/Api/MembershipController.php  7 个 API
  Http/Controllers/Api/PaymentController.php     9 个支付 API
database/
  migrations/2026_08_24_100000_create_membership_core_tables.php  14 张表
  migrations/2026_08_25_100001_create_payments_table.php          payments + subscriptions.payment_order_no
  migrations/2026_08_25_100002_add_cashier_columns_to_users.php   stripe_id 等 (Billable)
  seeders/MembershipSeeder.php  演示数据
config/payments.php  支付通道开关 (mock/wechat/alipay/stripe/wallet)
routes/api.php     prefix api/v1/membership + api/v1/payment
resources/views    welcome + Filament
```

---

## 4. 快速开始（买家 3 分钟跑起来）

```bash
# 1. 解压后安装
composer install
cp .env.example .env
php artisan key:generate

# 2. 建库 + 演示数据
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
php artisan migrate --seed
# 或一键: composer run setup

# 3. 启动
php artisan serve                 # http://127.0.0.1:8000
# 后台: http://127.0.0.1:8000/admin
# API:  http://127.0.0.1:8000/api/v1/health

# 4. 前端（如需二次开发）
npm install
npm run build   # 生产
npm run dev     # 开发
```

### 演示账号（seed 后）

| 角色 | 邮箱 | 密码 | 用途 |
|------|------|------|------|
| 商户管理员 | `tenant@demo.com` | `12345678` | 登录 `/admin` 管理会员/套餐/券 |
| 演示会员 | `demo@member.com` | `12345678` | 普通会员，无后台权限 |

---

## 5. API 一览 `routes/api.php`

```
# 会员
GET  /api/v1/health
GET  /api/v1/membership/plans?tenant_id=1
GET  /api/v1/membership/levels?tenant_id=1
GET  /api/v1/membership/profile/{userId}?tenant_id=1
POST /api/v1/membership/subscribe          {tenant_id,user_id,plan_id,payment_method}
POST /api/v1/membership/points/add         {tenant_id,user_id,points,type,description}
POST /api/v1/membership/wallet/recharge    {tenant_id,user_id,amount,type,description}
POST /api/v1/membership/feature/consume    {subscription_id,feature_code,amount}
# 支付 - 通用 + 兼顾 Cashier (mock/钱包/微信/支付宝/Stripe/线下)
POST /api/v1/payment                       {tenant_id,user_id,business_type,amount,channel,plan_id,coupon_id}
GET  /api/v1/payment?tenant_id=1&status=pending
GET  /api/v1/payment/{orderNo}
GET  /api/v1/payment/{orderNo}/query
POST /api/v1/payment/{orderNo}/mock-pay    # 演示一键付（仅对运行在 Mock 模式的渠道生效：mock 渠道 + 未配置密钥降级的 wechat/alipay/stripe）
POST /api/v1/payment/{orderNo}/cancel
POST /api/v1/payment/{orderNo}/mark-paid   # 管理员核销（需 Bearer token，角色 super_admin/admin/tenant_admin，先调 /api/v1/auth/login 换取）
POST /api/v1/payment/{orderNo}/refund      {amount}（需 Bearer token，同上）
POST /api/v1/payment/callback/{channel}    # wechat/alipay/stripe/mock
POST /api/v1/payment/webhook/stripe        # Stripe 专用
```

测试：
```bash
# 会员
curl http://127.0.0.1:8000/api/v1/membership/plans?tenant_id=1
curl http://127.0.0.1:8000/api/v1/membership/profile/1?tenant_id=1
# 支付 - 订阅(走支付单)
curl -X POST http://127.0.0.1:8000/api/v1/payment -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"user_id":1,"business_type":"subscription","plan_id":1,"channel":"mock"}'
# 演示一键付
curl -X POST http://127.0.0.1:8000/api/v1/payment/PAY20260825XXXXXX/mock-pay
# 钱包充值
curl -X POST http://127.0.0.1:8000/api/v1/payment -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"user_id":1,"business_type":"wallet_recharge","amount":100,"channel":"mock"}'
# 余额支付
curl -X POST http://127.0.0.1:8000/api/v1/payment -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"user_id":1,"business_type":"subscription","plan_id":2,"channel":"wallet"}'
```

服务层直接调用：
```php
// 旧直连（演示）
app(\App\Services\MembershipService::class)->subscribe(1, 1, 2, ['payment_method'=>'wechat']);
// 推荐：走支付单（自动创建 pending 订阅，支付成功后激活）
app(\App\Services\PaymentService::class)->create(1, 1, 'subscription', 19.9, 'mock', ['plan_id'=>1]);
app(\App\Services\PaymentService::class)->create(1, 1, 'wallet_recharge', 100, 'stripe', ['subject'=>'充值100']); // Stripe via Cashier
app(\App\Services\PaymentService::class)->markPaid($payment);
app(\App\Services\MembershipService::class)->addPoints(1, 1, 100, 'earn', '消费赠送');
app(\App\Services\MembershipService::class)->consumeFeature($subscriptionId, 'FREE_SHIPPING', 1);
```

> 生产环境建议给 `membership`/`payment` 路由组加 `auth:sanctum` + `tenant` 中间件，已预留接入点。
> 未配置 STRIPE_SECRET/WECHAT_PAY_* 时相应渠道自动降级 Mock，保证演示可跑；配置后自动走真实网关。

---

## 6. 后台 Filament

访问 `/admin`，12 个 Resources 已按导航分组：

- **系统管理:** Tenants, Branches
- **会员配置:** MembershipLevels, MembershipPlans, Features, Coupons
- **会员运营:** MemberProfiles, Subscriptions, PointLedgers, Wallets, Payments, CheckIns

Payments 后台支持：列表/筛选(渠道/状态)/标记已付/退款/查看 Stripe Session；Stripe 需配置 `STRIPE_SECRET` 后自动创建 Checkout Session。

`app/Providers/Filament/AdminPanelProvider.php:25` 已配置品牌色 Amber、中文品牌名。

---

## 7. 部署注意

- 生产把 `.env` 中 `APP_ENV=production` `APP_DEBUG=false`，`APP_KEY` 重新生成
- `DB_CONNECTION` 切 MySQL 时取消注释 `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- `storage` 与 `bootstrap/cache` 需可写
- 已含 `.gitignore` 忽略 `.env`/`vendor`/`node_modules`/`public/build`/`storage/*.key`

---

## 8. 支付配置（已完善，兼顾 Cashier）

| 渠道 | `.env` 键 | 说明 |
|------|-----------|------|
| `mock` | `PAYMENT_DEFAULT_CHANNEL=mock` | 演示默认，无需密钥，`/mock-pay` 一键成功 |
| `wallet` | `PAYMENT_WALLET_ENABLED=true` | 余额支付，自动扣减 `wallets.balance` |
| `manual` | - | 线下/人工，管理员在 Filament 点“标记已付” |
| `stripe` | `STRIPE_SECRET` / `STRIPE_KEY` / `STRIPE_WEBHOOK_SECRET` | 走 **Cashier 16.7 + stripe-php** Checkout Session，未配置自动降级 Mock |
| `wechat` | `WECHAT_PAY_MCH_ID` 等 | 预留 `yansongda/pay` 接入点，未安装/未配置降级 Mock |
| `alipay` | `ALIPAY_APP_ID` 等 | 同上 |

`User` 已 `Billable`，`AppServiceProvider` 已处理 Cashier 订阅表冲突（本项目 `subscriptions` 保留业务含义，不使用 Cashier 官方迁移）。

## 9. 可售化增强建议（已预留，按需收费升级）

1. **鉴权:** `laravel/sanctum` API Token + 封禁 `member_profiles.status=frozen`
2. **多租户域名:** Tenant 独立域名 + 安装向导
3. **PWA/小程序:** Laragym PWA 思路可直接复用 `check_ins`

---

## 10. 交付清单

- [x] 16 张表迁移 (14+payments+cashier columns) + 17 Models + MembershipService/PaymentService + 6 网关工厂
- [x] 7+9 API + Filament 12 Resources + Widgets
- [x] Cashier 16.7 + stripe-php 已接入（Stripe 渠道走 Checkout Session，未配置降级 Mock，兼顾方案）
- [x] MembershipSeeder 演示数据 + Spatie 角色权限
- [x] `composer.json` 已品牌化 `membership-pro/laravel-saas` (`laravel/cashier ^16.7`)
- [x] `.env.example` 支付开关 + `README` 销售文档
- [x] `LICENSE` 私有授权（见根目录）

---

## 11. 授权与售后

本项目为 **私有授权 (Proprietary)**，禁止二次转售源码，支持部署咨询。请保留 `LICENSE` 文件。

> 原始调研与聚合细节见 `README_MEMBERSHIP.md`（开发者笔记，不随包发给终端用户可删）。

---

**一句话卖点：** 一套 Laravel 买断，SaaS 多租户 + 付费订阅 + 积分储值券 全闭环，Filament 后台开箱即用，3 分钟跑起来直接交付客户。
