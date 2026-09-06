# Membership Pro — 可售版 Laravel 多租户 SaaS 会员 + 电商系统

> Laravel 13 + PHP 8.3 + Filament 5 + Spatie Permission + Cashier 16.7 | 21 张业务表全闭环 | 会员机制真实生效 | 商城 + 获客首页 | 开箱即售

![Laravel](https://img.shields.io/badge/Laravel-13.26-red) ![PHP](https://img.shields.io/badge/PHP-8.3-blue) ![Filament](https://img.shields.io/badge/Filament-5.7-orange) ![License](https://img.shields.io/badge/License-Proprietary-yellow)

> 🌐 **在线演示（https://fenshen66.xyz 已上线）**：**平台首页** <https://fenshen66.xyz/> · **示例店铺** <https://fenshen66.xyz/shop/demo> · **后台** <https://fenshen66.xyz/admin>（`tenant@demo.com / 12345678`）· **健康检查** <https://fenshen66.xyz/api/v1/health>

基于 GitHub 高星项目优点聚合（spatie/laravel-permission 12k★、soulbscription、laragym 等），单库支撑 **SaaS 多租户售卖 + 商城电商**：商户/店铺/商品/订单/等级/权益/套餐/订阅/积分/储值/优惠券/签到/审计 全闭环，附带面向商户的获客首页与 30 秒自助开店流程。

---

## 1. 功能清单（买家最关心的）

| 模块 | 表 | 卖点 |
|------|----|------|
| **多租户** | `tenants` + `users.tenant_id` | 一套代码卖 N 家商户；**后台数据按租户隔离**（作用域+观察者双层防护），面板准入按角色收紧 |
| **获客首页** | — | 面向商户的转化页：动态在营店铺墙、输入店名即预览域名、三步开店、收费明牌 |
| **自助开店** | `tenants` + `shops` | 30 秒入驻：登录用户可用**自己的账号**开店（自动建租户/总店/默认等级/管理员归属） |
| **商城电商** | `shops`/`products`/`orders`/`order_items` | 店铺货架、购物车、下单锁库存、平台抽佣、订单-支付单全链路 |
| **等级体系** | `membership_levels` | 普通/银/金/钻石，**等级折扣真实作用于下单**、积分倍率作用于入账 |
| **权益系统** | `features` + `feature_plan` | Soulbscription 模式：quota/boolean/discount 三类，套餐绑定配额，`consumeFeature()` 原子扣减 |
| **付费套餐** | `membership_plans` | 月卡/年卡/终身 + 7 种计费周期 + 试用期 + 推荐位；金额服务端强制，防改价 |
| **订阅** | `subscriptions` | 支付成功自动激活、**到期自动置 expired + 提前邮件提醒** |
| **积分** | `point_ledgers` 不可变账本 | `lockForUpdate` + `balance_after` 审计；**积分倍率按等级放大**；**下单积分抵现，取消自动退回** |
| **储值钱包** | `wallets` + `wallet_transactions` | 充值/消费/冻结，`bcadd` 精度，事务锁，支付单关联可追溯 |
| **优惠券** | `coupons` + `coupon_user` | 满减/折扣/包邮，限领/限用/有效期 |
| **考勤签到** | `check_ins` | 连签奖励；**同日唯一键防重放 + 同 IP 每日限流** |
| **保级/降级** | `member_profiles` | 周期成长值快照，不达标自动降级并重置基线（调度任务） |
| **审计日志** | `activity_logs` | 关键操作溯源（订阅过期/降级/核销等） |
| **支付** | `payments` + Cashier | mock/钱包/微信/支付宝/Stripe/线下，退款/回调原子化，**Stripe 真实退款 + webhook 强制验签** |
| **权限** | Spatie Permission | `super_admin`/`admin`/`tenant_admin`/`staff`/`member`；会员/支付写端点需 Bearer Token（或同域 session），积分/钱包调整与核销退款需管理员角色 |

**商业模式直接覆盖：** 88VIP 式付费会员 / 等级+积分 / 储值 / 券包 / 连锁多店 / 平台抽佣。

---

## 2. 技术栈

- **后端:** Laravel 13.26.1, PHP 8.3, Spatie Permission 8.3, Filament 5.7, Cashier 16.7 + stripe-php 20.3, Sanctum 4.3
- **前端:** Vite 8 + Tailwind 4 + Livewire (Filament 自带) + Alpine（商城前台）
- **数据库:** SQLite 开箱即用（可切 MySQL/PostgreSQL，改 `.env` 即可）
- **核心服务:** `MembershipService`（订阅/积分/储值/权益）+ `PaymentService`（支付编排/回调/退款）+ `OrderService`（Web/API 共用下单）+ 6 网关工厂
- **网关:** `mock` 演示 / `wallet` 余额 / `manual` 线下 / `wechat`/`alipay`（yansongda/pay 占位）/ `stripe`（stripe-php Checkout，未配置密钥自动降级 Mock）

---

## 3. 目录结构

```
app/
  Models/                    21 个模型 (含 Shop/Product/Order/OrderItem；User 为 Billable + FilamentUser)
  Models/Scopes/             TenantDataScope 租户数据隔离（全局作用域）
  Observers/                 TenantDataObserver 写入归属守护
  Services/MembershipService.php  订阅/积分(倍率)/储值/权益 核心
  Services/PaymentService.php     支付编排/业务钩子/回调/退款
  Services/OrderService.php       Web 与 API 共用下单（锁库存/等级折扣/积分抵现）
  Services/Payments/              GatewayInterface + 6 网关 (含 isMockMode 演示降级判定)
  Console/Commands/          payments:cancel-expired / subscriptions:expire / memberships:relevel
  Filament/Resources/        16 个后台资源（含审计日志可视化）+ AdminPanelProvider
  Http/Controllers/Api/      Membership / Payment / Order / Auth / TenantAuth
  Http/Controllers/Web/      ShopController（获客首页/商城/支付页/开店）
  Http/Middleware/           ResolveTenant（子域名/路径/header 多模式租户解析）
database/migrations/         会员核心 16 表 + payments + 电商 4 表 + 租户/会员增强迁移
config/payments.php          支付通道开关
config/membership.php        会员配置（积分抵现比例/提醒天数/保级周期/签到限流/演示店地址）
routes/api.php               api/v1: auth/tenants/membership/shop/payment
routes/web.php               获客首页/商城/支付页/开店/登录注册
```

---

## 4. 快速开始（买家 3 分钟跑起来）

```bash
# 1. 安装
composer install
cp .env.example .env
php artisan key:generate

# 2. 建库 + 演示数据
touch database/database.sqlite   # Windows: type nul > database\database.sqlite
php artisan migrate --seed
# 或一键: composer run setup

# 3. 启动
php artisan serve                 # http://127.0.0.1:8000
# 获客首页: http://127.0.0.1:8000/          (输入店名即预览域名)
# 示例店铺: http://127.0.0.1:8000/shop/demo
# 商户后台: http://127.0.0.1:8000/admin

# 4. 前端（如需二次开发）
npm install && npm run build
```

### 演示账号（seed 后）

| 角色 | 邮箱 | 密码 | 用途 |
|------|------|------|------|
| 商户管理员 | `tenant@demo.com` | `12345678` | 登录 `/admin`，数据仅限本租户 |
| 演示会员 | `demo@member.com` | `12345678` | 普通会员，商城购物/签到/订阅 |

---

## 5. API 一览 `routes/api.php`

```
# 认证（Sanctum Bearer Token）
POST /api/v1/auth/register                 {name,email,password,tenant_id|tenant_slug,...}
POST /api/v1/auth/login                    {email,password} -> token
POST /api/v1/auth/forgot-password          发送密码重置邮件（防枚举，限流）
POST /api/v1/auth/reset-password           重置密码 {email,token,password}
POST /api/v1/auth/logout / GET /me / PUT /profile    （需 Bearer token）

# B 端入驻
POST /api/v1/tenants/register              一键建 Tenant/Branch/Level/管理员
GET  /api/v1/tenants/check-slug?slug=

# 会员（plans/levels 公开，其余需 Bearer token；user_id 以登录态为准，管理员可代操作）
GET  /api/v1/health
GET  /api/v1/membership/plans?tenant_id=1
GET  /api/v1/membership/levels?tenant_id=1
GET  /api/v1/membership/profile/{userId}?tenant_id=1   仅本人或管理员
POST /api/v1/membership/subscribe          {tenant_id,plan_id}   # 金额服务端按套餐价强制
POST /api/v1/membership/feature/consume    {subscription_id,feature_code,amount}   仅订阅本人或管理员
POST /api/v1/membership/points/add         🔒 管理员 {tenant_id,user_id,points,type}  # earn 类型自动享受等级倍率
POST /api/v1/membership/wallet/recharge    🔒 管理员 {tenant_id,user_id,amount,type}

# 商城（支持子域名/path 自动识别租户）
GET  /api/v1/shop/products                 在售商品列表
POST /api/v1/shop/orders                   下单 {shop_id,items[],channel,coupon_id,use_points}  （需 Bearer token）
GET  /api/v1/shop/orders / /orders/{orderNo}  我的订单（需 Bearer token）

# 支付（除回调外均需 Bearer token；普通用户仅能操作本人支付单，管理员可跨用户）
POST /api/v1/payment                       {tenant_id,business_type,plan_id,channel,coupon_id}  # user_id 取登录态
GET  /api/v1/payment                       列表（仅本人；管理员可按 tenant/user 筛选）
GET  /api/v1/payment/{orderNo}  / {orderNo}/query    # query 含主动对账兜底
POST /api/v1/payment/{orderNo}/mock-pay    本人支付单 + 仅 Mock 模式渠道；生产环境整个 mock 通道禁用（PAYMENT_MOCK_ENABLED）
POST /api/v1/payment/{orderNo}/cancel      本人或管理员；订单业务自动回补库存/退回积分/释放优惠券名额
POST /api/v1/payment/{orderNo}/mark-paid   🔒 管理员核销（Bearer token + super_admin/admin/tenant_admin）
POST /api/v1/payment/{orderNo}/refund      🔒 退款（Stripe 走真实 Refund API；需 Bearer token，同上）
POST /api/v1/payment/callback/{channel}    网关回调（Stripe 强制验签；支付宝 RSA2 / 微信 V2 手工验签或 SDK；非成功交易状态不核销）
POST /api/v1/payment/webhook/stripe        Stripe webhook
```

服务层直接调用：
```php
// 推荐：走支付单（自动创建 pending 订阅，支付成功后激活；金额服务端强制按套餐价）
app(\App\Services\PaymentService::class)->create(1, 1, 'subscription', 0, 'mock', ['plan_id'=>1]);
app(\App\Services\PaymentService::class)->create(1, 1, 'wallet_recharge', 100, 'stripe', ['subject'=>'充值100']);
app(\App\Services\OrderService::class)->placeOrder($user, $tenant, $shopId, $items, 'mock', null, null, usePoints: true);
app(\App\Services\MembershipService::class)->addPoints(1, 1, 100, 'earn', '消费赠送'); // 金卡自动 ×2
app(\App\Services\MembershipService::class)->consumeFeature($subscriptionId, 'FREE_SHIPPING', 1);
```

> `membership` 写操作与商城下单建议生产环境补 `auth:sanctum`；`mark-paid`/`refund`/`cancel` 已内置鉴权/归属校验。
> 未配置 STRIPE_SECRET/WECHAT_PAY_* 时相应渠道自动降级 Mock（演示可跑），配置后自动走真实网关。
> **API 交互文档**：`/docs/api`（OpenAPI 3.1 自动生成，local 环境默认开放，生产环境可用 IP 白名单或按需关闭；scramble 为 dev 依赖，`composer install --no-dev` 的纯生产安装不含此路由）。本地模式下文档页每次实时分析控制器，**首次打开约 10 秒属正常**；生产使用建议用 `php artisan scramble:export` 导出静态文档。

---

## 6. 商城前台与获客首页

| 页面 | 说明 |
|------|------|
| `/` | 面向商户的**获客首页**：动态在营店铺墙、输入店名即预览独立域名、三步开店、5% 抽佣明牌、FAQ |
| `/shop/{slug}` 或 `summer.xxx.com` | 商户的独立品牌店：货架/购物车/结账/订单，会员等级折扣与积分抵现自动生效 |
| `/pricing` `/me` `/check-in` | 套餐购买（走支付单）、个人中心（会员/订阅/钱包）、每日签到（连签奖励） |
| `/pay/{orderNo}` | 支付页：**可切换支付渠道**（余额预检、换渠道自动重开支付单），Mock 一键付/真实收银台自适应 |
| `/tenants/register` | 自助入驻：未登录填管理员账号；**已登录用户可直接用当前账号开店** |

---

## 7. 后台 Filament

访问 `/admin`，15 个 Resources 按导航分组：

- **系统管理:** Tenants, Branches, ActivityLogs（审计日志，只读）
- **电商管理:** Shops, Products, Orders
- **会员配置:** MembershipLevels, MembershipPlans, Features, Coupons
- **会员运营:** MemberProfiles, Subscriptions, PointLedgers, Wallets, Payments, CheckIns

Payments 后台支持列表/筛选/标记已付/退款（直接调用 PaymentService，与 API 同一套事务逻辑）；Stripe 需配置 `STRIPE_SECRET` 后自动创建 Checkout Session。

**数据隔离：** 商户管理员登录后仅能读写自己租户的数据（列表/编辑/导出全链路过滤，越权写入被强制归属）；平台管理员（super_admin/admin）全量可见；租户支持自助入驻（web 表单与 `POST /api/v1/tenants/register`，已限流），平台管理员可后台新建。

---

## 8. 会员体系（全部真实生效）

| 机制 | 说明 | 触发点 |
|------|------|--------|
| 等级折扣 | 金卡 88 折等，下单自动计入 `discount_amount` | `OrderService` 下单链路 |
| 积分倍率 | 金卡 ×2、钻石 ×3，签到/消费入账自动放大，账本留痕 | `addPoints(earn)` |
| 积分抵现 | 100 积分 = 1 元（可配），下单勾选即抵，取消订单自动退回 | 商城结算页 `use_points` |
| 订阅到期 | 到期自动 `expired` + 前 N 天邮件提醒（防重发） | `subscriptions:expire`（每日） |
| 保级/降级 | 按周期成长值快照重算等级，不达标降级并写审计日志 | `memberships:relevel`（每日） |
| 签到风控 | 同日唯一键 + 同 IP 每日限流（默认 20 次/天） | 签到接口 |

---

## 9. 运维命令（生产需部署 scheduler）

```bash
# crontab: * * * * * php /path/artisan schedule:run
php artisan payments:cancel-expired    # 每 5 分钟：过期支付单自动取消（回补库存/取消订阅）
php artisan subscriptions:expire       # 每日 01:10：订阅到期置位 + 到期前 N 天邮件提醒
php artisan memberships:relevel        # 每日 02:10：保级周期重算（不达标降级）
```

---

## 10. 部署注意

- 生产把 `.env` 中 `APP_ENV=production` `APP_DEBUG=false`，`APP_KEY` 重新生成
- **泛子域名多租户上线**（泛域名 DNS + 泛域名 SSL + nginx 配置 + `TRUSTED_PROXIES`）见 `INSTALL.md`「生产部署」一节，含可复制的完整 nginx 配置
- `DB_CONNECTION` 切 MySQL 时取消注释 `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`
- **必须部署 scheduler**（见第 9 节 cron），否则过期取消/到期提醒/保级降级不会运行
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- `storage` 与 `bootstrap/cache` 需可写；邮件提醒需配置 SMTP（`MAIL_MAILER`）

### 会员相关环境变量（`config/membership.php`）

| 键 | 默认 | 说明 |
|----|------|------|
| `MEMBERSHIP_POINTS_PER_YUAN` | 100 | 多少积分抵 1 元 |
| `MEMBERSHIP_POINTS_REDEEM` | true | 是否开启下单积分抵现 |
| `MEMBERSHIP_SUBSCRIPTION_REMIND_DAYS` | 3 | 订阅到期前提醒天数 |
| `MEMBERSHIP_RELEVEL_MONTHS` | 12 | 保级周期（月） |
| `MEMBERSHIP_CHECKIN_IP_LIMIT` | 20 | 同 IP 每日签到上限 |
| `MEMBERSHIP_DEMO_STORE_URL` | - | 首页「示例店铺」入口指向的线上店铺 |
| `MEMBERSHIP_TENANT_AUTO_ACTIVATE` | true | 自助开店是否直接激活；**生产建议 false**（开店后 pending，平台后台审核激活，未激活租户的子域名/店铺不对外渲染） |
| `MEMBERSHIP_DEFAULT_TENANT_SLUG` | demo | 裸域访问 `/pricing` `/me` `/check-in` 等页面的兜底商户 |
| `MEMBERSHIP_MAX_PENDING_ORDERS` | 5 | 单用户未支付订单上限（pending 订单锁库存，防脚本刷单；0=不限制） |

---

## 11. 支付配置

| 渠道 | `.env` 键 | 说明 |
|------|-----------|------|
| `mock` | `PAYMENT_DEFAULT_CHANNEL=mock` | 演示默认，无需密钥，`/mock-pay` 一键成功 |
| `wallet` | `PAYMENT_WALLET_ENABLED=true` | 余额支付，自动扣减 `wallets.balance`；**钱包充值业务禁止用余额渠道支付** |
| `manual` | - | 线下/人工，管理员在 Filament 点“标记已付” |
| `stripe` | `STRIPE_SECRET` / `STRIPE_KEY` / `STRIPE_WEBHOOK_SECRET` | Checkout Session + **真实 Refund API**；webhook 强制验签 |
| `wechat` | `WECHAT_PAY_MCH_ID` 等 | 预留 `yansongda/pay` 接入点，未安装/未配置降级 Mock；**自动退款未实现**——对微信支付单发起退款会明确报错（不会谎报成功），请人工在商户平台退款 |
| `alipay` | `ALIPAY_APP_ID` 等 | 同上，**自动退款未实现**，请人工在商家后台退款 |

`User` 已 `Billable`，`AppServiceProvider` 已处理 Cashier 订阅表冲突（本项目 `subscriptions` 保留业务含义，不使用 Cashier 官方迁移）。

> **微信/支付宝完整接入步骤**（SDK 安装、密钥配置、启用下单代码、退款实现、验收清单）见 [`docs/payment-integration.md`](docs/payment-integration.md)。

---

## 12. 交付清单

- [x] 21 张业务表迁移 + 21 Models + MembershipService/PaymentService/OrderService + 6 网关工厂
- [x] 认证/入驻/会员/商城/支付 API + Filament 15 Resources + 获客首页 + 商城前台
- [x] 租户数据隔离（作用域 + 观察者 + 面板准入）
- [x] 会员机制全接线：等级折扣 / 积分倍率 / 积分抵现 / 订阅到期提醒 / 保级降级 / 签到风控
- [x] 运维命令 ×3 + 调度注册（支付过期 / 订阅到期 / 保级重算）
- [x] Stripe Checkout + 真实 Refund + webhook 验签；未配置密钥自动降级 Mock
- [x] MembershipSeeder 演示数据 + Spatie 角色权限 + `.env.example` 全量注释
- [x] `LICENSE` 私有授权（见根目录）

---

## 13. 授权与售后

本项目为 **私有授权 (Proprietary)**，禁止二次转售源码，支持部署咨询。请保留 `LICENSE` 文件。

> 原始调研与聚合细节见 `README_MEMBERSHIP.md`（开发者笔记，不随包发给终端用户可删）。

---

**一句话卖点：** 一套 Laravel 买断，SaaS 多租户 + 商城电商 + 付费订阅 + 等级积分储值券全闭环，会员机制开箱真实生效，Filament 后台按租户隔离，3 分钟跑起来直接交付客户。
