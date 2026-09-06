<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn()=> response()->json(['message'=>'Unauthenticated'], 401))->name('login');

Route::prefix('v1')->group(function () {
    Route::get('/health', fn()=> response()->json(['ok'=>true,'time'=>now()]));

    // B端商户入驻 - shop1.xxx.com 子域名模式（公开端点，限流防滥建租户）
    Route::prefix('tenants')->group(function () {
        Route::post('/register', [TenantAuthController::class, 'register'])->middleware('throttle:5,1');
        Route::get('/check-slug', [TenantAuthController::class, 'checkSlug'])->middleware('throttle:30,1');
    });

    // 认证 - 用户端注册/登录（不影响后台 /admin 登录）支持子域名自动解析 tenant
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        // 密码找回（发链接走邮件，无需登录）
        Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'apiSendResetLink'])->middleware('throttle:5,1');
        Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'apiReset'])->middleware('throttle:5,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
        });
    });
    // 会员相关 - 套餐/等级公开，其余需登录；积分/钱包变动需管理员角色
    Route::prefix('membership')->group(function () {
        Route::get('/plans', [MembershipController::class, 'plans']);
        Route::get('/levels', [MembershipController::class, 'levels']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/feature/consume', [MembershipController::class, 'consumeFeature']);
            Route::get('/profile/{userId}', [MembershipController::class, 'profile']);
        });
        Route::middleware(['auth:sanctum', 'role:super_admin|admin|tenant_admin'])->group(function () {
            // 会员开通只走支付流程（定价页/POST /payment business_type=subscription），此端点仅供管理员手工开通
            Route::post('/subscribe', [MembershipController::class, 'subscribe']);
            Route::post('/points/add', [MembershipController::class, 'addPoints']);
            Route::post('/wallet/recharge', [MembershipController::class, 'walletRecharge']);
        });
    });

    // 商城 - 电商前台 (支持 shop1.xxx.com 子域名自动隔离)
    Route::prefix('shop')->group(function () {
        Route::get('/products', [OrderController::class, 'products']);
        Route::get('/products/{id}', [OrderController::class, 'productShow']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/orders', [OrderController::class, 'create'])->middleware(['throttle:10,1', 'verify-email']); // 下单限流 + 邮箱验证门禁
            Route::get('/orders', [OrderController::class, 'list']);
            Route::get('/orders/{orderNo}', [OrderController::class, 'show']);
        });
    });

    // 支付 - 通用支付 + 兼顾 Cashier(Stripe)，支持 mock/钱包/微信/支付宝/Stripe/线下
    Route::prefix('payment')->group(function () {
        // 回调/ webhook 由网关验签保护，保持公开
        Route::post('/callback/{channel}', [PaymentController::class, 'callback']);
        Route::post('/webhook/stripe', [PaymentController::class, 'stripeWebhook']); // Stripe 专用

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/', [PaymentController::class, 'create'])->middleware(['throttle:30,1', 'verify-email']); // 创建支付单（user_id 取登录态，管理员可代付）
            Route::get('/', [PaymentController::class, 'list']); // 普通用户仅能看自己的
            Route::get('/{orderNo}', [PaymentController::class, 'show']);
            Route::get('/{orderNo}/query', [PaymentController::class, 'query']);
            Route::post('/{orderNo}/mock-pay', [PaymentController::class, 'mockPay']); // 演示一键付（本人支付单 + Mock 模式渠道）
            Route::post('/{orderNo}/cancel', [PaymentController::class, 'cancel']);
        });

        // 管理员操作：需登录 token + super_admin/admin/tenant_admin 角色
        Route::middleware(['auth:sanctum', 'role:super_admin|admin|tenant_admin'])->group(function () {
            Route::post('/{orderNo}/mark-paid', [PaymentController::class, 'markPaid']); // 管理员核销
            Route::post('/{orderNo}/refund', [PaymentController::class, 'refund']);
        });
    });
});
