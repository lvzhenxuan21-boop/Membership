<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\MemberProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\TenantProvisioning;
use Database\Seeders\MembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 安全与账务加固回归：退款空壳不再谎报成功、晚到款不吞单、
 * 未支付订单上限、优惠券回写订单金额、租户职员钉死本租户。
 */
class HardeningTest extends TestCase
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
            'name' => '测试商城', 'slug' => 'hardening-shop',
            'status' => 'active', 'platform_fee_rate' => 0.05,
        ]);
    }

    private function makeProduct(Shop $shop, float $price = 100, int $stock = 10): Product
    {
        return Product::create([
            'tenant_id' => $this->tenantId(), 'shop_id' => $shop->id,
            'name' => '加固测试商品', 'slug' => 'p-hardening-'.uniqid(),
            'price' => $price, 'stock' => $stock, 'sales' => 0, 'status' => 'on_sale',
        ]);
    }

    // ---------- 退款诚实性 ----------

    public function test_alipay_refund_does_not_fake_success(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]); // 测试环境支付宝未配置密钥 → mock 模式放行
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 100, 'alipay');
        $svc->markPaid($payment);

        // 支付宝自动退款未实现：必须报错，而不是把支付单标记 refunded（资金实际未退）
        try {
            $svc->refund($payment);
            $this->fail('支付宝退款应当失败而不是谎报成功');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('退款失败', $e->getMessage());
        }
        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals(0, (float)$payment->fresh()->refunded_amount);
    }

    public function test_wechat_refund_does_not_fake_success(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]);
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 100, 'wechat');
        $svc->markPaid($payment);

        $this->expectException(\RuntimeException::class);
        $svc->refund($payment);
    }

    // ---------- 晚到款（过期/取消后网关确认收款） ----------

    public function test_late_webhook_marks_expired_payment_paid_with_flag(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]);
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 10, 'mock');
        $payment->update(['expired_at' => now()->subMinutes(5)]);

        // 网关确认收款但本地已过期：仍核销（钱不能吞），并打晚到标记
        $result = $svc->handleCallback('mock', ['order_no' => $payment->order_no]);
        $this->assertEquals('paid', $result->status);
        $this->assertTrue($result->callback_data['late_mark'] ?? false);
    }

    public function test_webhook_for_cancelled_payment_is_recorded_not_resurrected(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]);
        $svc = app(PaymentService::class);
        $payment = $svc->create($this->tenantId(), $this->memberUser()->id, 'wallet_recharge', 10, 'mock');
        $svc->cancel($payment);

        // 已取消的单不能自动复活（库存/券名额可能已释放），但必须留痕告警
        $result = $svc->handleCallback('mock', ['order_no' => $payment->order_no]);
        $this->assertEquals('cancelled', $result->fresh()->status);
        $this->assertTrue($result->fresh()->callback_data['late_payment_unresolved'] ?? false);
    }

    // ---------- 刷单防护 ----------

    public function test_pending_order_cap_blocks_new_orders(): void
    {
        $this->seedDemo();
        config(['membership.max_pending_orders' => 2]);
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop);
        $svc = app(OrderService::class);

        $svc->placeOrder($this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'mock');
        $svc->placeOrder($this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'mock');

        try {
            $svc->placeOrder($this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]], 'mock');
            $this->fail('超出未支付订单上限应当被拒绝');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('未支付订单', $e->getMessage());
        }
        $this->assertEquals(2, Order::where('user_id', $this->memberUser()->id)->where('status', 'pending')->count());
    }

    // ---------- 优惠券与订单金额口径 ----------

    public function test_coupon_discount_is_written_back_to_order(): void
    {
        $this->seedDemo();
        $shop = $this->makeShop();
        $product = $this->makeProduct($shop, price: 100);
        $coupon = Coupon::create([
            'tenant_id' => $this->tenantId(), 'name' => '立减30', 'code' => 'SAVE30-'.uniqid(),
            'type' => 'cash', 'value' => 30, 'min_amount' => 0,
            'total_quota' => 10, 'per_user_limit' => 1, 'is_active' => true,
        ]);

        $order = app(OrderService::class)->placeOrder(
            $this->memberUser(), null, $shop->id, [['product_id' => $product->id, 'quantity' => 1]],
            'mock', $coupon->id
        );

        // 订单口径必须一致：total = discount(券) + points + pay
        $this->assertEquals(30, (float)$order->discount_amount);
        $this->assertEquals(0, (int)$order->points_used);
        $this->assertEquals(70, (float)$order->pay_amount);
        $this->assertEquals(100, (float)$order->total_amount);

        $payment = Payment::where('order_no', $order->payment_order_no)->firstOrFail();
        $this->assertEquals(70, (float)$payment->amount);
        $this->assertEquals(30, (float)$payment->discount_amount);
    }

    // ---------- 租户职员越权 ----------

    public function test_tenant_admin_pinned_to_own_tenant_for_points(): void
    {
        $this->seedDemo();
        // 建第二个租户 + 会员
        [$foreignTenant, $foreignAdmin] = app(TenantProvisioning::class)->provision('他商', 'foreign-tenant', null, null, [
            'name' => '外来管理员', 'email' => 'foreign@admin.com', 'password' => 'Abcdef12',
        ]);
        $foreignUser = User::create(['name' => '外来会员', 'email' => 'foreign@member.com', 'password' => 'Abcdef12']);
        $foreignUser->assignRole('member');
        MemberProfile::create(['user_id' => $foreignUser->id, 'tenant_id' => $foreignTenant->id, 'status' => 'active']);

        $admin = User::where('email', 'tenant@demo.com')->firstOrFail();

        // tenant_admin 传别人租户的 tenant_id + user_id：应被钉死/拒绝，而不是给外来用户加积分
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/membership/points/add', [
                'tenant_id' => $foreignTenant->id,
                'user_id' => $foreignUser->id,
                'points' => 999, 'type' => 'earn',
            ])
            ->assertStatus(422);
    }

    public function test_tenant_admin_cannot_query_other_tenant_payments(): void
    {
        $this->seedDemo();
        config(['payments.mock_enabled' => true]);
        [$foreignTenant, $foreignAdmin] = app(TenantProvisioning::class)->provision('他商二', 'foreign-tenant-2', null, null, [
            'name' => '外来管理员2', 'email' => 'foreign2@admin.com', 'password' => 'Abcdef12',
        ]);
        $foreignUser = User::create(['name' => '外来会员2', 'email' => 'foreign2@member.com', 'password' => 'Abcdef12']);
        $foreignUser->assignRole('member');
        MemberProfile::create(['user_id' => $foreignUser->id, 'tenant_id' => $foreignTenant->id, 'status' => 'active']);

        $svc = app(PaymentService::class);
        $foreignPayment = $svc->create($foreignTenant->id, $foreignUser->id, 'wallet_recharge', 10, 'mock');

        // 租户职员的全局作用域：直接模型查询也查不到他租户支付单
        $admin = User::where('email', 'tenant@demo.com')->firstOrFail();
        $this->actingAs($admin);
        $this->assertNull(Payment::where('order_no', $foreignPayment->order_no)->first());

        // API 详情同样 404
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/payment/{$foreignPayment->order_no}")
            ->assertStatus(404);
    }

    // ---------- 商户开通 ----------

    public function test_tenant_provisioning_can_create_pending_tenant_when_auto_activate_disabled(): void
    {
        $this->seedDemo();
        config(['membership.tenant_auto_activate' => false]);

        [$tenant, $admin] = app(TenantProvisioning::class)->provision('待审核商户', 'pending-tenant', null, null, [
            'name' => '待审管理员', 'email' => 'pending@admin.com', 'password' => 'Abcdef12',
        ]);

        $this->assertEquals('pending', $tenant->status);
        $this->assertTrue($admin->hasRole('tenant_admin'));
        $this->assertEquals($tenant->id, $admin->tenant_id);

        // 开启自动激活（默认）时直接 active
        config(['membership.tenant_auto_activate' => true]);
        [$active, ] = app(TenantProvisioning::class)->provision('直启商户', 'active-tenant', null, null, [
            'name' => '直启管理员', 'email' => 'active@admin.com', 'password' => 'Abcdef12',
        ]);
        $this->assertEquals('active', $active->status);
    }

    public function test_pending_tenant_is_not_resolved_for_storefront(): void
    {
        $this->seedDemo();
        config(['membership.tenant_auto_activate' => false]);
        [$tenant, $admin] = app(TenantProvisioning::class)->provision('待展示商户', 'hidden-tenant', null, null, [
            'name' => '隐藏管理员', 'email' => 'hidden@admin.com', 'password' => 'Abcdef12',
        ]);

        // path 模式 /shop/{slug}：pending 租户不渲染店铺（落到获客首页）
        $this->get('/shop/'.$tenant->slug)->assertViewIs('landing');
    }
}
