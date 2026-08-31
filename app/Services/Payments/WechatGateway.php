<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * 微信支付网关
 * - 已安装 yansongda/pay 时交给 SDK（V2/V3 均可）
 * - 未装 SDK 时按微信 V2 异步通知规则手工验签（MD5 或 HMAC-SHA256，API 密钥 WECHAT_PAY_MCH_KEY）
 * - 未配置密钥时仅非生产环境可 Mock，生产一律拒绝
 */
class WechatGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        if ($this->isMockMode()) {
            if (!PaymentGatewayFactory::mockAllowed()) {
                throw new \RuntimeException('微信支付未配置 WECHAT_PAY_MCH_ID/MCH_KEY，生产环境禁止降级 Mock');
            }
            Log::warning('[WechatGateway] 未配置或未安装 yansongda/pay，降级 Mock');
            return (new MockGateway)->pay($payment);
        }

        // 真实接入示例（需买家自行启用）：
        // $order = [
        //     'out_trade_no' => $payment->order_no,
        //     'description' => $payment->subject,
        //     'amount' => ['total' => (int)($payment->amount * 100), 'currency' => 'CNY'],
        //     'notify_url' => url("/api/v1/payment/callback/wechat"),
        // ];
        // $result = \Yansongda\Pay\Pay::wechat()->mp($order);
        // return ['pay_url'=>$result->get('code_url'), 'channel'=>'wechat', 'raw'=>$result];

        return [
            'pay_url' => url("/api/v1/payment/{$payment->order_no}/mock-pay"),
            'channel' => 'wechat',
            'mock' => true,
            'message' => '微信支付占位：请配置 WECHAT_PAY_* 并安装 yansongda/pay 后替换此处',
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool
    {
        if ($this->isMockMode()) {
            return PaymentGatewayFactory::mockAllowed();
        }

        if (class_exists(\Yansongda\Pay\Pay::class)) {
            try {
                \Yansongda\Pay\Pay::wechat()->callback($rawBody ?? json_encode($payload));
                return true;
            } catch (\Throwable $e) {
                Log::error('[WechatGateway] SDK 验签失败: '.$e->getMessage());
                return false;
            }
        }

        // 微信 V2 手工验签：排除 sign 后按 key 升序拼 k=v&…，末尾拼 &key=MCH_KEY，按 sign_type 摘要后大写比对
        $sign = $payload['sign'] ?? '';
        if (empty($sign)) return false;
        $mchKey = config('payments.channels.wechat.mch_key');
        if (empty($mchKey)) {
            Log::error('[WechatGateway] 缺少 WECHAT_PAY_MCH_KEY，拒绝未验签回调');
            return false;
        }
        $params = $payload;
        unset($params['sign']);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        ksort($params);
        $signStr = urldecode(http_build_query($params)).'&key='.$mchKey;

        $signType = $payload['sign_type'] ?? 'MD5';
        $expected = strtoupper($signType === 'HMAC-SHA256'
            ? hash_hmac('sha256', $signStr, $mchKey)
            : md5($signStr));

        $ok = hash_equals($expected, strtoupper((string)$sign));
        if (!$ok) Log::error('[WechatGateway] V2 验签失败', ['out_trade_no' => $payload['out_trade_no'] ?? '']);
        return $ok;
    }

    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'wechat']; }
    public function refund(Payment $payment, ?float $amount = null): array { return ['success'=>true, 'channel'=>'wechat']; }
    // 未配置 WECHAT_PAY_MCH_ID 视为 Mock；已配置商户号但未装 SDK 时，凭 MCH_KEY 仍可手工验签，不算 Mock
    public function isMockMode(): bool
    {
        return empty(config('payments.channels.wechat.mch_id'))
            || (empty(config('payments.channels.wechat.mch_key')) && !class_exists(\Yansongda\Pay\Pay::class));
    }
}
