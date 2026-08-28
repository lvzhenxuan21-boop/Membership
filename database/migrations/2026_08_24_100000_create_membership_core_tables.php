<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 租户/商户 - 支撑SaaS多租户售卖 (参考 Laratrust teams + Liberu多模块)
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 商户名
            $table->string('slug')->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->enum('status', ['active','suspended','trial'])->default('active');
            $table->json('settings')->nullable(); // 扩展配置
            $table->timestamps();
        });

        // 2. 门店/分支 - 来自 laragym 多分支优势
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->enum('status', ['active','closed'])->default('active');
            $table->timestamps();
        });

        // 3. 会员等级 - 传统积分等级体系底座
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // 如 普通/银卡/金卡/钻石
            $table->string('slug');
            $table->integer('level')->default(1); // 数值越大等级越高
            $table->bigInteger('min_points')->default(0); // 升级门槛积分
            $table->bigInteger('min_growth')->default(0); // 成长值门槛
            $table->decimal('discount_rate', 5, 2)->default(1.00); // 95折=0.95
            $table->integer('points_multiplier')->default(1); // 积分加倍
            $table->json('benefits')->nullable(); // 权益描述
            $table->string('badge_color')->default('#999999');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id','slug']);
        });

        // 4. 权益 Feature - 来自 soulbscription 的 Feature 消费模型
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // 如 免费配送/专属优惠券/优先客服
            $table->string('slug')->unique();
            $table->string('code')->unique(); // 业务编码 FEATURE_FREE_SHIPPING
            $table->enum('type', ['quota','boolean','discount'])->default('boolean');
            $table->string('unit')->nullable(); // 次/张
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. 付费套餐/计划 Plans - 来自 Soulbscription + Liberu + laravel-subscriptions
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // 月卡/年卡/终身VIP
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('CNY');
            $table->enum('billing_cycle', ['lifetime','monthly','quarterly','yearly','weekly','daily','fixed'])->default('monthly');
            $table->integer('duration_days')->nullable(); // 固定时长天数，billing_cycle=fixed时用
            $table->integer('trial_days')->default(0);
            $table->json('benefits')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id','slug']);
        });

        // 6. Plan <-> Feature 关联 + 配额
        Schema::create('feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->string('value')->nullable(); // boolean=true/false, quota=100, discount=0.85
            $table->integer('quota')->nullable(); // 次数配额
            $table->timestamps();
            $table->unique(['membership_plan_id','feature_id']);
        });

        // 7. 会员扩展信息 - 挂在 users 表上 (支持多租户)
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('membership_level_id')->nullable()->constrained('membership_levels')->nullOnDelete();
            $table->string('member_no')->unique(); // 会员编号 M20260001
            $table->string('real_name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('id_card')->nullable();
            $table->date('birthday')->nullable();
            $table->enum('gender', ['unknown','male','female'])->default('unknown');
            $table->string('avatar')->nullable();
            $table->bigInteger('points')->default(0); // 当前积分
            $table->bigInteger('growth')->default(0); // 成长值
            $table->decimal('balance', 10, 2)->default(0); // 储值余额冗余
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->enum('status', ['active','frozen','cancelled'])->default('active');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id','user_id']);
            $table->index(['tenant_id','membership_level_id']);
        });

        // 8. 订阅 - 用户购买的Plan实例 (Soulbscription核心)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->string('order_no')->unique();
            $table->enum('status', ['pending','active','cancelled','expired','paused'])->default('pending');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // 到期时间
            $table->timestamp('cancelled_at')->nullable();
            $table->string('payment_method')->nullable(); // wechat/alipay/stripe
            $table->string('payment_id')->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','user_id','status']);
        });

        // 9. 订阅内 Feature 消耗记录 - Ticket/消耗模型
        Schema::create('subscription_feature_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->integer('used')->default(0);
            $table->integer('quota')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id','feature_id']);
        });

        // 10. 积分流水 - 原子化账本 (参考 Tashil immutable event store)
        Schema::create('point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['earn','spend','refund','expire','adjust'])->index();
            $table->bigInteger('points'); // 正数earn/负数spend
            $table->bigInteger('balance_after'); // 变动后余额
            $table->string('source_type')->nullable(); // order/checkin/admin
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->string('operator')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','user_id','created_at']);
        });

        // 11. 钱包/储值
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('frozen', 10, 2)->default(0);
            $table->decimal('total_recharged', 10, 2)->default(0);
            $table->decimal('total_consumed', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id','user_id']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['recharge','consume','refund','freeze','unfreeze','adjust']);
            $table->decimal('amount', 10, 2); // 正负
            $table->decimal('balance_after', 10, 2);
            $table->string('order_no')->nullable();
            $table->string('description')->nullable();
            $table->string('operator')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['wallet_id','created_at']);
        });

        // 12. 优惠券
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['discount','cash','free_shipping'])->default('cash');
            $table->decimal('value', 10, 2); // 满减金额或折扣率
            $table->decimal('min_amount', 10, 2)->default(0); // 满多少可用
            $table->integer('total_quota')->default(0); // 0不限
            $table->integer('per_user_limit')->default(1);
            $table->integer('issued_count')->default(0);
            $table->integer('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code'); // 领取实例码
            $table->enum('status', ['unused','used','expired'])->default('unused');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);
        });

        // 13. 活动日志 - 来自 laragym activity logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // member.recharge / subscription.create
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','action','created_at']);
        });

        // 14. 签到/考勤 - 复用 laragym attendance
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_in_at')->useCurrent();
            $table->string('method')->default('qrcode'); // qrcode/manual
            $table->timestamps();
            $table->index(['tenant_id','user_id','checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('coupon_user');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('point_ledgers');
        Schema::dropIfExists('subscription_feature_usages');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('features');
        Schema::dropIfExists('membership_levels');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
