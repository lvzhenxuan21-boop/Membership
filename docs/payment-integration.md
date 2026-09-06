# 微信支付 / 支付宝接入指南

> 本系统的支付架构：`PaymentService` 统一编排，`app/Services/Payments/` 下每个渠道一个网关类。
> **Stripe 已完整可用**（Checkout + 退款 + Webhook 验签，配好 `STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET` 即生效）。
> 微信/支付宝内置了**验签逻辑与接入点**，真实下单需要按本指南安装 SDK 并启用两段注释代码，全程约 10 分钟。

## 0. 支付流程速览（先读懂这个再动手）

```
买家下单/订阅
   └─ PaymentService::create()          事务内：占优惠券名额 → 建 pending 支付单(+) pending 订阅
        └─ 事务提交后：Gateway::pay()    出网调用（此处生成收款链接）
             └─ 网关异步回调 POST /api/v1/payment/callback/{channel}
                  └─ Gateway::verifyWebhook()  验签 → markPaid() → 触发业务（激活订阅/订单销量/佣金）
```

要点（系统已内置，你无需处理）：
- 回调**只信异步通知**，验签失败一律拒绝；重复回调幂等（已支付直接返回）；
- 状态非成功（如 `WAIT_BUYER_PAY`）不会核销；
- 金额服务端强制（订阅按套餐价），客户端传参改不动价格；
- 过期支付单由调度任务 `payments:cancel-expired` 每 5 分钟自动取消并回补库存/释放优惠券。

---

## 1. 支付宝接入

### 1.1 安装 SDK 并填密钥

```bash
composer require yansongda/pay
```

`.env`（在 [支付宝开放平台](https://open.alipay.com) 创建应用后获取）：

```ini
ALIPAY_ENABLED=true
ALIPAY_APP_ID=2021000000000000
ALIPAY_PRIVATE_KEY=你的应用私钥            # 用于本系统手工验签兜底与 SDK 配置
ALIPAY_PUBLIC_KEY=支付宝公钥               # 注意是"支付宝公钥"不是应用公钥
```

### 1.2 初始化 SDK（AppServiceProvider::boot）

```php
// config/payments 密钥 → yansongda/pay v3（具体字段以你安装版本的官方文档为准）
\Yansongda\Pay\Pay::config([
    'alipay' => [
        'default' => [
            'app_id' => config('payments.channels.alipay.app_id'),
            'merchant_private_key' => config('payments.channels.alipay.private_key'),
            // 证书模式需要以下三项（公钥模式可省略，推荐用官方密钥工具生成）
            'app_public_cert_path' => storage_path('certs/alipayAppPublicCert.crt'),
            'alipay_public_cert_path' => storage_path('certs/alipayPublicCert.crt'),
            'app_secret_cert' => storage_path('certs/alipayAppSecretKey.crt'),
            'notify_url' => url('/api/v1/payment/callback/alipay'),
        ],
    ],
    'logger' => ['enable' => true, 'file' => storage_path('logs/pay.log')],
    'http' => ['timeout' => 5.0, 'connect_timeout' => 5.0],
]);
```

### 1.3 启用下单代码（唯一需要你写的代码）

打开 `app/Services/Payments/AlipayGateway.php` 的 `pay()`，把注释块换成：

```php
$result = \Yansongda\Pay\Pay::alipay()->web([
    'out_trade_no' => $payment->order_no,
    'total_amount' => (string) $payment->amount,   // 单位元，两位小数
    'subject'      => $payment->subject,
]);
return ['pay_url' => $result->get('pay_url'), 'channel' => 'alipay'];
```

完成后本系统自动：
- 回调进入 `verifyWebhook()` → 已装 SDK 时交给 `Pay::alipay()->callback()` 验签；
- 验签通过且 `trade_status` 为 `TRADE_SUCCESS/TRADE_FINISHED` 才核销。

### 1.4 退款（可选，默认人工）

系统对支付宝退款**默认明确报错**（防止"本地标退款、钱没退"的假成功）。要开自动退款，在 `AlipayGateway::refund()` 实现：

```php
\Yansongda\Pay\Pay::alipay()->refund([
    'out_trade_no' => $payment->order_no,
    'refund_amount' => (string) ($amount ?? $payment->amount),
]);
return ['success' => true, 'channel' => 'alipay'];
```

---

## 2. 微信支付接入

### 2.1 安装 SDK 并填密钥

```bash
composer require yansongda/pay   # 与支付宝同一个 SDK
```

`.env`（[微信商户平台](https://pay.weixin.qq.com) 申请，推荐 V3 API）：

```ini
WECHAT_PAY_ENABLED=true
WECHAT_PAY_MCH_ID=1900000000
WECHAT_PAY_APP_ID=wx1234567890abcdef
WECHAT_PAY_MCH_KEY=你的APIv2密钥          # 未装 SDK 时的手工验签兜底
```

### 2.2 初始化 SDK（AppServiceProvider::boot，V3 证书在商户平台下载）

```php
\Yansongda\Pay\Pay::config([
    'wechat' => [
        'default' => [
            'mch_id' => config('payments.channels.wechat.mch_id'),
            'mch_secret_key' => env('WECHAT_PAY_V3_KEY'),            // APIv3 密钥（32 位）
            'mch_secret_cert' => storage_path('certs/wechatApiclient_key.pem'),
            'mch_public_cert_path' => storage_path('certs/wechatApiclient_cert.pem'),
            'notify_url' => url('/api/v1/payment/callback/wechat'),
            'mp_app_id' => config('payments.channels.wechat.app_id'),
        ],
    ],
    'logger' => ['enable' => true, 'file' => storage_path('logs/pay.log')],
]);
```

### 2.3 启用下单代码

`app/Services/Payments/WechatGateway.php` 的 `pay()`：

```php
// Native 扫码支付（PC 商城），公众号/小程序内改用 ->mp() / ->mini()
$result = \Yansongda\Pay\Pay::wechat()->native([
    'out_trade_no' => $payment->order_no,
    'description'  => $payment->subject,
    'amount'       => ['total' => (int) round($payment->amount * 100)],  // 单位：分
]);
return ['pay_url' => $result->get('code_url'), 'channel' => 'wechat'];  // 二维码内容，前台渲染二维码
```

### 2.4 退款（可选，默认人工）

同支付宝：默认报错提示人工退款；要自动退款在 `WechatGateway::refund()` 用 SDK 实现后返回 `['success'=>true]`。

---

## 3. 验收清单（上线前逐项打勾）

- [ ] 回调地址公网可达：`https://你的域名/api/v1/payment/callback/alipay`（本地开发用 ngrok/内网穿透）
- [ ] 发起一笔 0.01 元真实支付，确认：支付单变 `paid`、订阅激活/订单 `paid` 且销量 +1
- [ ] 同一笔回调重放（商户平台有重发功能），确认幂等不重复核销
- [ ] 在网关后台对这笔订单发起退款，确认 `PaymentService::refund()` 行为符合你的实现（自动/人工）
- [ ] `PAYMENT_MOCK_ENABLED` 保持为空；`APP_ENV=production` 下 mock 渠道已被强制禁用
- [ ] 日志无 `验签失败` 告警（`storage/logs/pay.log` 与 Laravel 日志）

## 4. 常见问题

| 现象 | 原因与处理 |
|------|-----------|
| 回调 422「验签失败」 | 密钥配错：支付宝要用**支付宝公钥**（不是应用公钥）；微信 V2 手工验签用 APIv2 密钥，V3 用 SDK + 证书 |
| 回调 404 / 无法到达 | `APP_URL` 配错或回调地址未走公网；检查 nginx 是否放行 POST |
| 支付了但订单未变 paid | 先查支付单 `callback_data`；未收到回调时 `GET /api/v1/payment/{orderNo}/query` 可主动对账核销（Stripe） |
| 生产环境报「禁止降级 Mock」 | 密钥没配全（`isMockMode()` 判定未通过），按 1.1/2.1 检查 `.env` |
| 渠道选择里没有微信/支付宝 | 前台渠道下拉由 `PaymentGatewayFactory::channels()` 决定，已含全部渠道；确认 `.env` 已启用 |

> 支付宝沙箱环境可用于联调（沙箱网关与密钥在开放平台"沙箱"页）；微信支付无公开沙箱，用真实商户号小额（0.01 元）联调。
