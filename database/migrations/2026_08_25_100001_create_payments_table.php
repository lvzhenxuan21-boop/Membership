<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_no')->unique(); // PAY202608250001
            $table->string('transaction_no')->nullable()->index(); // 网关侧流水号
            $table->enum('business_type', ['subscription','wallet_recharge','order','other'])->default('subscription');
            $table->unsignedBigInteger('business_id')->nullable(); // 关联 subscription_id 等
            $table->string('subject'); // 显示标题 如 年卡VIP
            $table->decimal('amount', 10, 2); // 实付
            $table->decimal('original_amount', 10, 2)->nullable(); // 原价
            $table->decimal('discount_amount', 10, 2)->default(0); // 优惠
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('currency', 3)->default('CNY');
            $table->enum('channel', ['wechat','alipay','stripe','wallet','manual','mock'])->default('mock');
            $table->enum('status', ['pending','paid','failed','cancelled','refunded','partial_refunded'])->default('pending')->index();
            $table->string('pay_url')->nullable(); // 跳转链接/二维码内容
            $table->json('channel_data')->nullable(); // 网关返回原始
            $table->json('callback_data')->nullable(); // 回调原始
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable(); // 支付超时
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','user_id','status']);
            $table->index(['tenant_id','business_type','business_id']);
        });

        // 给 subscriptions 增加 payment 关联字段（如已存在则跳过）
        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'payment_order_no')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('payment_order_no')->nullable()->after('payment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'payment_order_no')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('payment_order_no');
            });
        }
        Schema::dropIfExists('payments');
    }
};
