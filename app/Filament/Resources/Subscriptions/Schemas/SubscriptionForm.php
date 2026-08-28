<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('membership_plan_id')
                    ->relationship('plan', 'name')
                    ->required()->label('套餐'),
                TextInput::make('order_no')
                    ->required()->label('订单号'),
                Select::make('status')
                    ->options(['pending'=>'待支付','active'=>'生效中','cancelled'=>'已取消','expired'=>'已过期','paused'=>'暂停'])
                    ->required()->default('pending')->label('状态'),
                DateTimePicker::make('trial_ends_at'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('payment_method'),
                TextInput::make('payment_id'),
                TextInput::make('paid_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
