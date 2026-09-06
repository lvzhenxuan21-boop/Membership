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

## 生产部署（nginx + 泛子域名多租户）

多租户子域名模式（`shop1.xxx.com` / `demo.xxx.com`）需要以下三件事，缺一不可：

### 1. 泛域名 DNS 解析

在域名服务商处添加一条 A 记录：

```
类型: A   主机记录: *   记录值: 服务器公网 IP
```

这样 `*.xxx.com` 的所有子域名都会指向你的服务器（主域 `xxx.com` 另配一条 `@` 记录）。

### 2. 泛域名 SSL 证书

通配符证书不能用 HTTP-01 文件验证，需用 DNS 验证。以 acme.sh + DNSPod 为例：

```bash
# 其他 DNS 服务商替换对应 dns_api 插件（阿里云 dns_ali、Cloudflare dns_cf 等）
export DP_Id="xxx" DP_Key="xxx"
acme.sh --issue --dns dns_dp -d "xxx.com" -d "*.xxx.com"
acme.sh --install-cert -d "xxx.com" \
  --key-file /etc/nginx/ssl/xxx.com.key \
  --fullchain-file /etc/nginx/ssl/xxx.com.crt
```

### 3. nginx 站点配置

```nginx
server {
    listen 443 ssl;
    server_name xxx.com *.xxx.com;          # 泛子域名必须写上
    root /var/www/membership/public;
    index index.php;

    ssl_certificate     /etc/nginx/ssl/xxx.com.crt;
    ssl_certificate_key /etc/nginx/ssl/xxx.com.key;

    # 传递真实 IP/协议给 Laravel（配合 .env 的 TRUSTED_PROXIES=*）
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

server {
    listen 80;
    server_name xxx.com *.xxx.com;
    return 301 https://$host$request_uri;
}
```

### 4. 反向代理下的 .env 配置

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://xxx.com            # 子域名按此拼接，影响域名预览/回调地址
TRUSTED_PROXIES=*                  # nginx/CDN 后必须设置，否则限流与签到 IP 风控全部失效
MEMBERSHIP_TENANT_AUTO_ACTIVATE=false   # 生产强烈建议：新店铺进后台审核后再激活
PAYMENT_MOCK_ENABLED=              # 保持为空/false
```

> 安全提示：`TRUSTED_PROXIES=*` 仅适用于所有流量都经过可信代理的场景。
> 若 PHP 直接暴露公网（无代理），请删除该变量，否则客户端可伪造 `X-Forwarded-For` 绕过 IP 限流。

### 5. 上线前检查清单

- [ ] 改掉 seed 演示账号密码（`tenant@demo.com / 12345678`），或 seed 后直接删掉演示数据
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] 部署 scheduler：`* * * * * php /path/artisan schedule:run`（过期单取消/订阅到期/保级降级依赖它）
- [ ] 配置真实支付渠道密钥（Stripe 完整可用；微信/支付宝需安装 `yansongda/pay` 按代码内注释接入）
- [ ] `PAYMENT_MOCK_ENABLED` 保持为空/false，防止伪造支付
- [ ] 配置数据库定时备份（MySQL：crontab mysqldump 并异地保存；SQLite：低峰期直接拷贝 `database/database.sqlite`）

## 演示数据重置

```bash
php artisan migrate:fresh --seed --force
```
