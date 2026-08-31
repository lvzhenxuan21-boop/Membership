<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MemberProfile;
use App\Models\MembershipLevel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreBusinessTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed(MembershipSeeder::class);
    }

    private function memberUser(): User
    {
        return User::where('email', 'demo@member.com')->firstOrFail();
    }

    private function tenantId(): int
    {
        return Tenant::where('slug', 'demo')->firstOrFail()->id;
    }

    private function makeShop(): Shop
    {
        $branch = Branch::where('tenant_id', $this->tenantId())->firstOrFail();
        return Shop::create([
            'tenant_id' => $this->tenantId(), 'branch_id' => $branch->id,
            'name' => '测试商城', 'slug' => 'test-shop',
            'status' => 'active', 'platform_fee_rate' => 0.05,
        ]);
    }

    private function makeProduct(Shop $shop, float $price = 100, int $stock = 10): Product
    {
        return Product::create([
            'tenant_id' => $this->tenantId(), 'shop_id' => $shop->id,
            'name' => '测试商品-'.$price, 'slug' => 'p-'.$price.'-'.uniqid(),
            'price' => $price, 'stock' => $stock, 'sales' => 0, 'status' => 'on_sale',
        ]);
    }

    // ---------- OrderService ----------

    public function test_place_order_deducts_stock_and_creates_payment(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 100, stock: 10);

        $order = app(OrderService::class)->placeOrder(
            $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 3]], 'mock'
        );

        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertEquals(300, (float)$order->total_amount);
        $this->assertEquals(300, (float)$order->pay_amount); // normal 等级无折扣
        $this->assertNotNull($order->payment_order_no);
        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals($order->id, $payment->business_id);
    }

    public function test_insufficient_stock_rolls_back_whole_transaction(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 100, stock: 2);

        try {
            app(OrderService::class)->placeOrder(
                $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 5]], 'mock'
            );
            $this->fail('应当抛出库存不足');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('库存不足', $e->getMessage());
        }

        $this->assertEquals(2, $product->fresh()->stock, '事务回滚后库存不变');
        $this->assertEquals(0, Order::where('shop_id', $shop->id)->count());
        $this->assertEquals(0, Payment::where('business_type', 'order')->count());
    }

    public function test_points_redemption_reduces_pay_amount_and_points(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 100, stock: 10); // 100积分=1元
        $user = $this->memberUser();

        $order = app(OrderService::class)->placeOrder(
            $user, null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]],
            'mock', null, null, usePoints: true
        );

        // 会员初始 100 积分，商品 100 元 → 100 积分抵 1 元，应付 99
        $this->assertEquals(100, $order->points_used);
        $this->assertEquals(1, (float)$order->points_amount);
        $this->assertEquals(99, (float)$order->pay_amount);
        $this->assertEquals(0, $user->memberProfile()->first()->points, '下单即扣减积分');
    }

    public function test_cancel_order_payment_restores_stock_and_refunds_points(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 100, stock: 10);
        $user = $this->memberUser();
        $svc = app(PaymentService::class);

        $order = app(OrderService::class)->placeOrder(
            $user, null, $shop->id, [['product_id' => $product->id, 'quantity' => 2]],
            'mock', null, null, usePoints: true
        );
        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        $stockAfterOrder = $product->fresh()->stock;

        $svc->cancel($payment);

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals($stockAfterOrder + 2, $product->fresh()->stock, '取消回补库存');
        $this->assertEquals(100, $user->memberProfile()->first()->points, '取消退回积分');
    }

    public function test_marking_order_paid_increments_sales(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 50, stock: 10);

        $order = app(OrderService::class)->placeOrder(
            $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 2]], 'mock'
        );
        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        app(PaymentService::class)->markPaid($payment);

        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals(2, $product->fresh()->sales);
    }

    public function test_wallet_channel_order_with_insufficient_balance_aborts(): void
    {
        $this->seedDemo(); // 会员钱包余额 0
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 50, stock: 5);

        try {
            app(OrderService::class)->placeOrder(
                $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'wallet'
            );
            $this->fail('余额不足应当中止下单');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('余额不足', $e->getMessage());
        }
        $this->assertEquals(5, $product->fresh()->stock, '事务回滚库存不变');
        $this->assertEquals(0, Order::where('shop_id', $shop->id)->count(), '不残留死单');
        $this->assertEquals(0, Payment::where('business_type', 'order')->count());
    }

    public function test_off_sale_product_rejected(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop);
        $product->update(['status' => 'off_sale']);

        $this->expectException(\RuntimeException::class);
        app(OrderService::class)->placeOrder(
            $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'mock'
        );
    }

    // ---------- MembershipService ----------

    public function test_earn_points_applies_level_multiplier(): void
    {
        $this->seedDemo();
        $user = $this->memberUser();
        $profile = $user->memberProfile()->first();
        $gold = MembershipLevel::where('tenant_id', $this->tenantId())->where('slug', 'gold')->firstOrFail();
        $profile->update(['membership_level_id' => $gold->id]); // 金卡倍率 ×2

        $ledger = app(MembershipService::class)->addPoints($this->tenantId(), $user->id, 10, 'earn', '测试入账');

        $this->assertEquals(20, $ledger->points, '金卡 10 积分入账放大为 20');
        $this->assertEquals(120, $profile->fresh()->points);
        $this->assertEquals(20, $ledger->balance_after - 100);
    }

    public function test_spend_more_than_balance_rejected(): void
    {
        $this->seedDemo(); // 会员初始 100 积分
        $profile = $this->memberUser()->memberProfile()->first();

        try {
            app(MembershipService::class)->addPoints($this->tenantId(), $this->memberUser()->id, -200, 'spend', '超额消费');
            $this->fail('应当抛出积分不足');
        } catch (\RuntimeException $e) {
            $this->assertEquals('积分不足', $e->getMessage());
        }
        $this->assertEquals(100, $profile->fresh()->points, '失败消费不扣积分');
    }

    public function test_adjust_points_not_multiplied(): void
    {
        $this->seedDemo();
        $user = $this->memberUser();
        $gold = MembershipLevel::where('tenant_id', $this->tenantId())->where('slug', 'gold')->firstOrFail();
        $user->memberProfile()->first()->update(['membership_level_id' => $gold->id]);

        $ledger = app(MembershipService::class)->addPoints($this->tenantId(), $user->id, 10, 'adjust', '人工调整');

        $this->assertEquals(10, $ledger->points, 'adjust 类型不享受倍率');
    }

    public function test_wallet_recharge_and_consume_with_balance_guard(): void
    {
        $this->seedDemo();
        $svc = app(MembershipService::class);
        $user = $this->memberUser();

        $tx1 = $svc->walletChange($this->tenantId(), $user->id, 100.55, 'recharge', '充值');
        $this->assertEquals('100.55', (string)$tx1->balance_after);
        $this->assertEquals('100.55', (string)Wallet::where('tenant_id', $this->tenantId())->where('user_id', $user->id)->first()->balance);

        $tx2 = $svc->walletChange($this->tenantId(), $user->id, -0.55, 'consume', '消费');
        $this->assertEquals('100.00', (string)$tx2->balance_after, 'bcadd 精度无浮点误差');

        try {
            $svc->walletChange($this->tenantId(), $user->id, -100.01, 'consume', '超额消费');
            $this->fail('应当抛出余额不足');
        } catch (\RuntimeException $e) {
            $this->assertEquals('余额不足', $e->getMessage());
        }
        $this->assertEquals('100.00', (string)Wallet::where('tenant_id', $this->tenantId())->where('user_id', $user->id)->first()->balance);
    }

    public function test_consume_feature_quota_and_unknown_feature(): void
    {
        $this->seedDemo();
        $svc = app(MembershipService::class);
        $user = $this->memberUser();
        // 月卡VIP: FREE_SHIPPING 配额 5 次
        $sub = $svc->subscribe($this->tenantId(), $user->id, 1);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($svc->consumeFeature($sub->id, 'FREE_SHIPPING'));
        }
        $usage = Subscription::findOrFail($sub->id)->featureUsages()->whereHas('feature', fn($q) => $q->where('code', 'FREE_SHIPPING'))->first();
        $this->assertEquals(5, $usage->used);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('权益次数已耗尽');
        $svc->consumeFeature($sub->id, 'FREE_SHIPPING');
    }

    public function test_consume_feature_not_in_plan_rejected(): void
    {
        $this->seedDemo();
        $sub = app(MembershipService::class)->subscribe($this->tenantId(), $this->memberUser()->id, 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未包含在此套餐');
        app(MembershipService::class)->consumeFeature($sub->id, 'NOT_EXISTS');
    }
}
