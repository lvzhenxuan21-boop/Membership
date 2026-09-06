# Changelog

格式参考 [Keep a Changelog](https://keepachangelog.com/)；版本号语义化（主.次.修订）。

## [1.1.0] - 2026-09-07

### 新增
- **密码找回**：Web 表单（`/forgot-password`、`/reset-password/{token}`）+ API 端点（`POST /api/v1/auth/forgot-password`、`POST /api/v1/auth/reset-password`），防邮箱枚举 + 限流，依赖 SMTP 配置
- **可选邮箱验证**：`MEMBERSHIP_EMAIL_VERIFICATION=true` 开启后，注册触发验证邮件，未验证会员下单/订阅/签到被引导至验证页（默认关闭，不影响演示）
- **平台佣金看板**：后台仪表盘展示累计/本月/今日抽佣、GMV 与近 7 天趋势（只计已收订单）
- **后台建租户走统一开通流程**：自动创建总店与默认等级，可选同步创建管理员账号，杜绝"裸租户"
- **后台会员调整入口**：「调整积分 / 调整余额」Action，经 MembershipService 产生流水，与 API 端权限对齐
- 支付接入文档 `docs/payment-integration.md`；API 文档（scramble，`/docs/api`，local 环境）
- 品牌名去硬编码：统一读 `APP_NAME`，买家改 `.env` 即完成白标
- 商品封面图片上传（FileUpload + `storage:link`，兼容旧 URL 数据）；购物车按租户隔离，跨店不再混单
- 审计日志后台可视化（系统管理 → 审计日志，只读）
- 基础安全响应头（X-Frame-Options / nosniff / Referrer-Policy / Permissions-Policy）
- 认证邮件队列化（验证/重置邮件不阻塞请求，`QUEUE_CONNECTION=database` + queue:work 生效）

### 收紧/修复
- 支付宝/微信退款不再谎报成功（明确报错转人工），对账兜底只对真实渠道生效
- 晚到款处理：网关确认收款但本地过期 → 补核销打 `late_mark`；已取消单留痕告警不复活
- 后台支付单/订单改只读 + `PaymentPolicy`；核销/退款按钮限管理员角色
- 会员档案资金字段只读，禁止手建档案；优惠券计数器只读
- 全额退款冲销订单：转 `refunded` + 回补库存 + 回滚销量 + 退回抵现积分
- 钱包退款同步冲减 `total_consumed` 统计
- 优惠券折扣回写订单金额，订单与支付单口径一致（`total = discount + points + pay`）
- 钱包充值禁止用余额渠道支付；`PAYMENT_WALLET_ENABLED` 真正生效
- 外部网关 HTTP 调用移出 DB 事务；租户作用域/观察者按角色生效；租户管理员操作钉死本租户
- 未支付订单上限 + 下单限流；`TRUSTED_PROXIES` 支持；泛子域名部署文档

### 测试
- 65 个测试 / 183 断言，SQLite 与 MySQL 8 双引擎全绿

## [1.0.0] - 2026-09-05

- 首个可售版本：多租户 SaaS + 商城电商 + 会员体系（等级/积分/储值/券/签到/订阅/保级）全闭环，6 渠道支付工厂，Filament 后台 15 资源，获客首页与自助开店
