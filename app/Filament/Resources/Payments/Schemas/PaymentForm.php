<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')->relationship('tenant','name')->required()->label('商户'),
            Select::make('user_id')->relationship('user','name')->required()->label('用户'),
            TextInput::make('order_no')->required()->label('支付单号')->disabled()->dehydrated(false),
            Select::make('business_type')->options(['subscription'=>'订阅','wallet_recharge'=>'充值','order'=>'订单','other'=>'其他'])->required()->label('业务类型'),
            TextInput::make('business_id')->numeric()->label('业务ID'),
            TextInput::make('subject')->required()->label('标题'),
            TextInput::make('amount')->numeric()->required()->prefix('¥')->label('实付'),
            TextInput::make('original_amount')->numeric()->prefix('¥')->label('原价'),
            TextInput::make('discount_amount')->numeric()->prefix('¥')->default(0)->label('优惠'),
            Select::make('channel')->options(['mock'=>'模拟','wechat'=>'微信','alipay'=>'支付宝','stripe'=>'Stripe(Cashier)','wallet'=>'余额','manual'=>'线下'])->required()->label('渠道'),
            Select::make('status')->options(['pending'=>'待支付','paid'=>'已支付','failed'=>'失败','cancelled'=>'已取消','refunded'=>'已退款','partial_refunded'=>'部分退款'])->required()->label('状态'),
            TextInput::make('pay_url')->label('支付链接')->columnSpanFull(),
            Textarea::make('channel_data')->label('渠道原始')->columnSpanFull()->rows(3),
            DateTimePicker::make('paid_at')->label('支付时间'),
            DateTimePicker::make('expired_at')->label('过期时间'),
        ]);
    }
}
