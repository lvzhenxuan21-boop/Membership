<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('order_no')->disabled()->label('订单号'),
            Select::make('status')->options(['pending'=>'待付款','paid'=>'已付款','shipped'=>'已发货','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款'])->required(),
            TextInput::make('total_amount')->numeric()->prefix('¥')->disabled(),
            TextInput::make('pay_amount')->numeric()->prefix('¥')->disabled()->label('实付'),
            TextInput::make('platform_fee')->numeric()->prefix('¥')->disabled()->label('平台抽佣'),
            TextInput::make('payment_channel')->label('支付渠道')->disabled(),
            TextInput::make('paid_at')->label('支付时间')->disabled(),
        ]);
    }
}
