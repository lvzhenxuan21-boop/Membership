# 升级指南

## 任意版本 → 最新版（通用步骤）

```bash
# 0. 备份
cp .env .env.bak && mysqldump -uUSER -p DBNAME > backup-$(date +%F).sql
# SQLite: 拷贝 database/database.sqlite

# 1. 覆盖源码（保留 .env 与 storage/）

# 2. 依赖与迁移
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# 3. 重建缓存
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:upgrade
```

## 1.0.x → 1.1.0

1. 迁移会新增/调整结构（租户 pending 状态等），`php artisan migrate --force` 即可，无破坏性数据变更
2. `.env` 可选新增（全部有安全默认值，不加也能跑）：
   - `APP_VERSION=1.1.0`
   - `TRUSTED_PROXIES=*`（nginx/CDN 后部署**必须**，见 INSTALL「生产部署」）
   - `MEMBERSHIP_TENANT_AUTO_ACTIVATE=false`（生产建议：开店进审核）
   - `MEMBERSHIP_EMAIL_VERIFICATION=true`（开启邮箱验证，需已配置 SMTP）
3. 采购版注意：后台支付单/订单/会员档案的资金字段已改为只读，相关操作移至列表页 Action（标记已付/退款/调整积分/调整余额），权限与 API 端一致
