<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentService;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundFlowTest extends TestCase
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

    // 年卡VIP 199 元，mock 渠道支付成功，返回 [payment, subscription]
    private function paidSubscription(): array
    {
        $plan = \App\Models\MembershipPlan::where('tenant_id', $this->tenantId())->where('slug', 'yearly')->firstOrFail();
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $this->memberUser()->id, 'subscription', 199, 'mock', ['plan_id' => $plan->id]);
        $payment = $svc->markPaid($payment);
        $sub = Subscription::findOrFail($payment->business_id);
        return [$payment, $sub];
    }

    public function test_full_refund_marks_refunded_and_cancels_subscription(): void
    {
        $this->seedDemo();
        [$payment, $sub] = $this->paidSubscription();
        $this->assertEquals('active', $sub->status);

        $refunded = app(PaymentService::class)->refund($payment);

        $this->assertEquals('refunded', $refunded->status);
        $this->assertEquals(199, (float)$refunded->refunded_amount);
        $this->assertEquals('cancelled', $sub->fresh()->status, '全额退款取消订阅');
    }

    public function test_double_refund_is_rejected(): void
    {
        $this->seedDemo();
        [$payment, $sub] = $this->paidSubscription();
        $svc = app(PaymentService::class);
        $svc->refund($payment);

        try {
            $svc->refund($payment->fresh());
            $this->fail('重复退款应当被拒绝');
        } catch (\RuntimeException $e) {
            // refunded 状态不在可退列表，由第一道守卫拦截
            $this->assertEquals('仅已支付订单可退款', $e->getMessage());
        }
        $this->assertEquals(199, (float)$payment->fresh()->refunded_amount, '退款金额未重复累计');
    }

    public function test_partial_refund_then_remaining_refundable(): void
    {
        $this->seedDemo();
        [$payment, $sub] = $this->paidSubscription();
        $svc = app(PaymentService::class);

        $partial = $svc->refund($payment, 50);
        $this->assertEquals('partial_refunded', $partial->status);
        $this->assertEquals(50, (float)$partial->refunded_amount);
        $this->assertEquals('active', $sub->fresh()->status, '部分退款保留订阅');

        $full = $svc->refund($payment->fresh());
        $this->assertEquals('refunded', $full->status);
        $this->assertEquals(199, (float)$full->refunded_amount);
        $this->assertEquals('cancelled', $sub->fresh()->status);
    }

    public function test_refund_more_than_remaining_is_capped(): void
    {
        $this->seedDemo();
        [$payment, $sub] = $this->paidSubscription();
        $svc = app(PaymentService::class);

        $partial = $svc->refund($payment, 150);
        $this->assertEquals(150, (float)$partial->refunded_amount, '退款额封顶到剩余可退');

        $full = $svc->refund($payment->fresh(), 999);
        $this->assertEquals(199, (float)$full->refunded_amount);
        $this->assertEquals('refunded', $full->status);
    }

    public function test_refunded_payment_keeps_coupon_consumed(): void
    {
        $this->seedDemo();
        $coupon = \App\Models\Coupon::where('code', 'WELCOME10')->firstOrFail();
        $user = $this->memberUser();
        $plan = \App\Models\MembershipPlan::where('tenant_id', $this->tenantId())->where('slug', 'yearly')->firstOrFail();
        $svc = app(PaymentService::class);

        $payment = $svc->create($this->tenantId(), $user->id, 'subscription', 199, 'mock', ['plan_id' => $plan->id, 'coupon_id' => $coupon->id]);
        $svc->markPaid($payment);
        $svc->refund($payment->fresh());

        // 券语义：用了就算，退款不返还名额，也不允许该用户再次使用
        $this->assertEquals(1, (int)$coupon->fresh()->used_count);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已达使用上限');
        $svc->create($this->tenantId(), $user->id, 'subscription', 199, 'mock', ['plan_id' => $plan->id, 'coupon_id' => $coupon->id]);
    }

    public function test_wallet_refund_credits_balance_back(): void
    {
        $this->seedDemo();
        $user = $this->memberUser();
        $svc = app(PaymentService::class);
        $balance = fn() => Wallet::where('tenant_id', $this->tenantId())->where('user_id', $user->id)->first()->balance;

        // 预充值 200，用余额购买年卡（199），退款后余额应全额回补
        app(\App\Services\MembershipService::class)->walletChange($this->tenantId(), $user->id, 200, 'recharge', '预充值');
        $plan = \App\Models\MembershipPlan::where('tenant_id', $this->tenantId())->where('slug', 'yearly')->firstOrFail();
        $payment = $svc->create($this->tenantId(), $user->id, 'subscription', 199, 'wallet', ['plan_id' => $plan->id]);
        $payment = $svc->markPaid($payment); // 钱包渠道创建时即时扣款 200 -> 1
        $this->assertEquals('1.00', (string)$balance());

        $refunded = $svc->refund($payment->fresh());
        $this->assertEquals('refunded', $refunded->status);
        $this->assertEquals('200.00', (string)$balance(), '退款回余额');
        $this->assertEquals('cancelled', \App\Models\Subscription::findOrFail($payment->business_id)->status);
    }

    // ---------- 订单全额退款的冲销语义 ----------

    private function makeShopAndProduct(float $price = 50, int $stock = 10): array
    {
        $branch = \App\Models\Branch::where('tenant_id', $this->tenantId())->firstOrFail();
        $shop = \App\Models\Shop::create([
            'tenant_id' => $this->tenantId(), 'branch_id' => $branch->id,
            'name' => '退款测试商城', 'slug' => 'refund-shop-'.uniqid(),
            'status' => 'active', 'platform_fee_rate' => 0.05,
        ]);
        $product = \App\Models\Product::create([
            'tenant_id' => $this->tenantId(), 'shop_id' => $shop->id,
            'name' => '退款测试商品', 'slug' => 'p-refund-'.uniqid(),
            'price' => $price, 'stock' => $stock, 'sales' => 0, 'status' => 'on_sale',
        ]);
        return [$shop, $product];
    }

    public function test_full_refund_of_order_reverses_order_stock_sales_and_points(): void
    {
        $this->seedDemo();
        $user = $this->memberUser();
        $svc = app(PaymentService::class);
        [$shop, $product] = $this->makeShopAndProduct(price: 50, stock: 10);

        // 下单 2 件 + 积分抵现（100 积分抵 1 元），支付成功
        $order = app(\App\Services\OrderService::class)->placeOrder(
            $user, null, $shop->id, [['product_id' => $product->id, 'quantity' => 2]],
            'mock', null, null, usePoints: true
        );
        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        $svc->markPaid($payment);
        $stockAfterSale = $product->fresh()->stock;
        $pointsAfterSpend = $user->memberProfile()->first()->points;
        $this->assertEquals(2, $product->fresh()->sales);

        // 全额退款 → 订单转 refunded + 回补库存 + 回滚销量 + 退回抵现积分
        $refunded = $svc->refund($payment->fresh());
        $this->assertEquals('refunded', $refunded->status);
        $this->assertEquals('refunded', $order->fresh()->status, '订单状态随全额退款冲销');
        $this->assertEquals($stockAfterSale + 2, $product->fresh()->stock, '退款回补库存');
        $this->assertEquals(0, $product->fresh()->sales, '退款回滚销量');
        $this->assertEquals($pointsAfterSpend + 100, $user->memberProfile()->first()->points, '退回下单抵现的 100 积分');
    }

    public function test_wallet_channel_refund_decrements_total_consumed(): void
    {
        $this->seedDemo();
        $user = $this->memberUser();
        $svc = app(PaymentService::class);
        app(\App\Services\MembershipService::class)->walletChange($this->tenantId(), $user->id, 100, 'recharge', '预充值');
        [$shop, $product] = $this->makeShopAndProduct(price: 40, stock: 5);

        $order = app(\App\Services\OrderService::class)->placeOrder(
            $user, null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'wallet'
        );
        $wallet = Wallet::where('tenant_id', $this->tenantId())->where('user_id', $user->id)->first();
        $this->assertEquals('60.00', (string)$wallet->balance);
        $this->assertEquals('40.00', (string)$wallet->total_consumed);

        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        $svc->refund($payment->fresh());
        $wallet->refresh();
        $this->assertEquals('100.00', (string)$wallet->balance, '退款回余额');
        $this->assertEquals('0.00', (string)$wallet->total_consumed, '退款同步冲减累计消费统计');
    }
}
