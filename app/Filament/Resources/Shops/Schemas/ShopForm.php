<?php

namespace App\Filament\Resources\Shops\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')->relationship('tenant','name')->required()->searchable()->preload(),
            Select::make('branch_id')->relationship('branch','name')->searchable()->preload(),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('slug')->required()->maxLength(50)->helperText('子域名前缀，如 shop1 对应 shop1.xxx.com')->unique(ignoreRecord:true),
            TextInput::make('logo')->label('Logo URL'),
            TextInput::make('contact_phone')->tel(),
            Textarea::make('description')->columnSpanFull(),
            Select::make('status')->options(['active'=>'营业','closed'=>'关闭','suspended'=>'冻结'])->default('active')->required(),
            TextInput::make('platform_fee_rate')->numeric()->step(0.0001)->default(0.05)->helperText('平台抽佣 0.05=5%'),
        ]);
    }
}
