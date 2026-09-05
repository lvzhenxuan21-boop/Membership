<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class AlipayGateway implements GatewayInterface
{
    public function pay(Payment $payment): array
    {
        if ($this->isMockMode()) {
            if (!PaymentGatewayFactory::mockAllowed()) {
                throw new \RuntimeException('支付宝未配置 ALIPAY_APP_ID/密钥，生产环境禁止降级 Mock');
            }
            Log::warning('[AlipayGateway] 未配置或未安装 yansongda/pay，降级 Mock');
            return (new MockGateway)->pay($payment);
        }

        // yansongda/pay 已安装时走真实网关（买家接入点）
        if (class_exists(\Yansongda\Pay\Pay::class)) {
            // $order = ['out_trade_no'=>$payment->order_no, 'total_amount'=>$payment->amount, 'subject'=>$payment->subject, 'notify_url'=>url('/api/v1/payment/callback/alipay')];
            // $result = \Yansongda\Pay\Pay::alipay()->web($order);
            // return ['pay_url'=>$result->get('pay_url'), 'channel'=>'alipay', 'raw'=>$result->all()];
        }

        // 未完成接入时诚实报错（支付单保持 pending 可重试），绝不返回假的 mock 支付链接
        throw new \RuntimeException('支付宝渠道未完成接入：请安装 yansongda/pay 并按注释启用真实下单');
    }

    /**
     * 异步通知验签（RSA2）：
     * - 已安装 yansongda/pay：交给 SDK 验签
     * - 否则按支付宝规则手工验签：排除 sign/sign_type 后按 key 升序拼 k=v&…，用支付宝公钥 SHA256 验签
     * - 未配置密钥（Mock 模式）：仅非生产环境放行，生产一律拒绝
     */
    public function verifyWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): bool
    {
        if ($this->isMockMode()) {
            return PaymentGatewayFactory::mockAllowed();
        }

        if (class_exists(\Yansongda\Pay\Pay::class)) {
            try {
                \Yansongda\Pay\Pay::alipay()->callback($rawBody ?? json_encode($payload));
                return true;
            } catch (\Throwable $e) {
                Log::error('[AlipayGateway] SDK 验签失败: '.$e->getMessage());
                return false;
            }
        }

        $sign = $payload['sign'] ?? '';
        if (empty($sign)) return false;
        $publicKey = config('payments.channels.alipay.public_key');
        if (empty($publicKey)) {
            Log::error('[AlipayGateway] 缺少 ALIPAY_PUBLIC_KEY，拒绝未验签回调');
            return false;
        }
        $pem = str_starts_with($publicKey, '-----') ? $publicKey
            : "-----BEGIN PUBLIC KEY-----\n".chunk_split($publicKey, 64, "\n")."-----END PUBLIC KEY-----\n";

        $params = $payload;
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        ksort($params);
        $signStr = implode('&', array_map(
            fn($k, $v) => $k.'='.$v,
            array_keys($params),
            array_values($params)
        ));

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            Log::error('[AlipayGateway] ALIPAY_PUBLIC_KEY 格式无效，拒绝回调');
            return false;
        }
        $ok = false;
        try {
            $ok = openssl_verify($signStr, base64_decode((string)$sign), $key, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (!$ok) Log::error('[AlipayGateway] RSA2 验签失败', ['order_no' => $payload['out_trade_no'] ?? '']);
        return $ok;
    }

    public function query(Payment $payment): array { return ['status'=>$payment->status, 'channel'=>'alipay']; }
    // 退款未实现：绝不能谎报成功（本地标 refunded 而资金未动会污染账务），由 PaymentService 转为明确报错
    public function refund(Payment $payment, ?float $amount = null): array
    {
        return ['success'=>false, 'channel'=>'alipay', 'error'=>'支付宝自动退款未实现：请在支付宝商家后台人工退款'];
    }
    // 未配置 ALIPAY_APP_ID 视为 Mock；已配置 app_id 但未装 SDK 时，凭 ALIPAY_PUBLIC_KEY 仍可手工验签，不算 Mock
    public function isMockMode(): bool
    {
        return empty(config('payments.channels.alipay.app_id'))
            || (empty(config('payments.channels.alipay.public_key')) && !class_exists(\Yansongda\Pay\Pay::class));
    }
}
