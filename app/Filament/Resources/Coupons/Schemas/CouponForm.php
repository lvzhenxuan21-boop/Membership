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
                TextInput::make('issued_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('used_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
