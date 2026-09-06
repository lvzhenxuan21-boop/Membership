<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('cash'),
                TextInput::make('value')
                    ->required()
                    ->numeric(),
                TextInput::make('min_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_quota')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('per_user_limit')
                    ->required()
                    ->numeric()
                    ->default(1),
                // 发放/使用计数由系统维护（领取/核销/取消自动增减），手改会破坏限领限用配额
                TextInput::make('issued_count')
                    ->numeric()->default(0)->label('已发放')->disabled()->dehydrated(false),
                TextInput::make('used_count')
                    ->numeric()->default(0)->label('已使用')->disabled()->dehydrated(false),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
