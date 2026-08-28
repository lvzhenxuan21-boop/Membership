<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 店铺 - 每个 Tenant 可多店铺，多商户核心
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('slug'); // shop1
            $table->string('logo')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active','closed','suspended'])->default('active');
            $table->decimal('platform_fee_rate', 5, 4)->default(0.05); // 平台抽佣 5%
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id','slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('sales')->default(0);
            $table->enum('status', ['draft','on_sale','off_sale'])->default('on_sale');
            $table->json('specs')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','shop_id','status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_no')->unique();
            $table->enum('status', ['pending','paid','shipped','completed','cancelled','refunded'])->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('pay_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->string('payment_channel')->nullable(); // wechat/alipay/stripe/wallet/mock
            $table->string('payment_order_no')->nullable();
            $table->json('address')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id','shop_id','user_id','status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shops');
    }
};
