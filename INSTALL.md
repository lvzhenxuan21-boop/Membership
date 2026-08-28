# 安装指南 - Membership Pro

## 环境要求
- PHP ^8.3 (ext-bcmath, ext-mbstring, ext-pdo_sqlite 或 pdo_mysql)
- Composer 2.x
- Node 18+ / npm 10+（仅二次开发前端需要）
- SQLite 默认，无需额外建库；MySQL 8+ 也可

## 5 步安装

```bash
# 1. 解压
unzip membership-pro.zip && cd membership-pro

# 2. 依赖
composer install --no-dev --optimize-autoloader
# 如需开发: composer install

# 3. 环境
cp .env.example .env
php artisan key:generate
# 按需改 .env: APP_NAME、APP_URL、DB_CONNECTION、MAIL_*

# 4. 数据库
touch database/database.sqlite
php artisan migrate --seed --force
# MySQL: 先建库，再改 .env 的 DB_*，然后同样 migrate --seed

# 5. 启动
php artisan serve
# 访问 http://localhost:8000/admin
```

### 前端（可选）

```bash
npm install
npm run build   # 生产产物到 public/build
npm run dev     # 开发热更新
```

### 生产优化

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:upgrade
```

确保 `storage/` 与 `bootstrap/cache/` 可写：

```bash
chmod -R 775 storage bootstrap/cache
```

## 常见问题

- **APP_KEY empty**: 执行 `php artisan key:generate`
- **database.sqlite not found**: `touch database/database.sqlite` 后重跑 migrate
- **Filament 404**: 执行 `php artisan filament:upgrade` 与 `php artisan route:cache`
- **权限登不进后台**: 确认用户角色为 `tenant_admin`/`admin`/`super_admin`，`demo@member.com` 是普通会员无后台权限，请用 `tenant@demo.com / 12345678`
- **切 MySQL**: `.env` 改 `DB_CONNECTION=mysql` 并填 `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`

## 演示数据重置

```bash
php artisan migrate:fresh --seed --force
```
