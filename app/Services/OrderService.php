<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Tenant;
use App\Models\User;
use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 商城下单服务 - Web(ShopController::placeOrder) 与 API(OrderController::create) 共用的唯一实现
 * 流程：校验店铺 -> 事务内锁商品扣库存 -> 建订单+明细 -> 建支付单(business_type=order) -> 回写支付单号
 * 校验失败抛 InvalidArgumentException/RuntimeException，表现层（JSON 422 或重定向报错）由控制器决定
 */
class OrderService
{
    public function __construct(private PaymentService $paymentService, private MembershipService $membershipService) {}

    public function placeOrder(User $user, ?Tenant $tenant, int $shopId, array $items, ?string $channel = null, ?int $couponId = null, ?array $address = null, bool $usePoints = false): Order
    {
        $shop = Shop::findOrFail($shopId);
        if ($tenant && $shop->tenant_id !== $tenant->id) {
            throw new \InvalidArgumentException('店铺不属于当前商户');
        }
        if ($shop->status !== 'active') {
            throw new \InvalidArgumentException('店铺已关闭');
        }

        return DB::transaction(function () use ($user, $shop, $items, $channel, $couponId, $address, $usePoints) {
            $orderNo = 'ORD'.date('YmdHis').strtoupper(Str::random(6));
            $total = 0;
            $itemsData = [];
            foreach ($items as $it) {
                $p = Product::where('shop_id', $shop->id)->lockForUpdate()->findOrFail($it['product_id']);
                if ($p->status !== 'on_sale') throw new \RuntimeException("商品 {$p->name} 已下架");
                if ($p->stock < $it['quantity']) throw new \RuntimeException("商品 {$p->name} 库存不足");
                $p->decrement('stock', $it['quantity']);
                $amount = round((float)$p->price * $it['quantity'], 2);
                $total += $amount;
                $itemsData[] = ['product'=>$p, 'quantity'=>$it['quantity'], 'amount'=>$amount];
            }

            // 等级折扣：买家在本租户会员等级的 discount_rate（0.95=95折），平台抽佣仍按原价计
            $levelDiscount = 0;
            $profile = MemberProfile::where('tenant_id', $shop->tenant_id)->where('user_id', $user->id)->with('level')->first();
            $rate = $profile?->level?->discount_rate;
            if ($rate !== null && (float)$rate > 0 && (float)$rate < 1) {
                $levelDiscount = min($total, round($total * (1 - (float)$rate), 2));
            }
            $payAmount = round($total - $levelDiscount, 2);

            // 积分抵现：100积分=1元（config membership.points_per_yuan），最多抵扣到折后应付为 0
            $pointsUsed = 0;
            $pointsAmount = 0;
            if ($usePoints && config('membership.points_redeem_enabled', true) && $payAmount > 0) {
                $perYuan = max(1, (int) config('membership.points_per_yuan', 100));
                $profile = MemberProfile::where('tenant_id', $shop->tenant_id)->where('user_id', $user->id)->first();
                if ($profile) {
                    $pointsUsed = min((int) $profile->points, (int) floor($payAmount * $perYuan));
                    if ($pointsUsed > 0) {
                        $pointsAmount = round($pointsUsed / $perYuan, 2);
                        $payAmount = round($payAmount - $pointsAmount, 2);
                    }
                }
            }

            $order = Order::create([
                'tenant_id'=>$shop->tenant_id,
                'shop_id'=>$shop->id,
                'user_id'=>$user->id,
                'order_no'=>$orderNo,
                'status'=>'pending',
                'total_amount'=>$total,
                'discount_amount'=>$levelDiscount,
                'pay_amount'=>$payAmount,
                'points_used'=>$pointsUsed,
                'points_amount'=>$pointsAmount,
                'platform_fee'=>round($total * (float)$shop->platform_fee_rate, 2),
                'payment_channel'=>$channel ?? config('payments.default_channel','mock'),
                'address'=>$address,
            ]);
            foreach ($itemsData as $d) {
                OrderItem::create([
                    'order_id'=>$order->id,
                    'product_id'=>$d['product']->id,
                    'product_name'=>$d['product']->name,
                    'price'=>$d['product']->price,
                    'quantity'=>$d['quantity'],
                    'amount'=>$d['amount'],
                ]);
            }

            // 扣减积分（同事务；订单取消时在 cancelOrderRestoreStock 中退回）
            if ($pointsUsed > 0) {
                $this->membershipService->addPoints($shop->tenant_id, $user->id, -$pointsUsed, 'spend', "下单积分抵现 {$orderNo}", 'order', $order->id);
            }

            $payment = $this->paymentService->create(
                $shop->tenant_id, $user->id, 'order', $payAmount,
                $channel ?? config('payments.default_channel','mock'),
                [
                    'business_id'=>$order->id,
                    'subject'=>"商城订单 {$orderNo}",
                    'coupon_id'=>$couponId,
                    'original_amount'=>(float)$total,
                    'meta'=>['order_no'=>$orderNo, 'shop_id'=>$shop->id],
                ]
            );
            $order->update(['payment_order_no'=>$payment->order_no]);

            return $order->fresh()->load('items','shop');
        });
    }
}
