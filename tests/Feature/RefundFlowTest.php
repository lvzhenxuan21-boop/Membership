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
}
