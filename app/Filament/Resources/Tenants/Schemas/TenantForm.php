<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('contact_name'),
                TextInput::make('contact_phone')
                    ->tel(),
                Select::make('status')
                    ->options([
                        'pending' => '待审核（店铺不对外）',
                        'active' => '在营',
                        'suspended' => '已停用',
                        'trial' => '试用',
                    ])
                    ->required()
                    ->default('active'),
                Textarea::make('settings')
                    ->columnSpanFull(),
                // 创建时可选同步创建管理员（否则只建租户壳，稍后可再绑定账号）
                // dehydrated(filled)：留空时不提交，避免空值触发 email/密码规则导致建租户失败
                TextInput::make('admin_name')
                    ->label('管理员姓名（选填）')
                    ->dehydrated(fn ($state) => filled($state))
                    ->visibleOn('create'),
                TextInput::make('admin_email')
                    ->email()
                    ->unique('users', 'email')
                    ->dehydrated(fn ($state) => filled($state))
                    ->label('管理员邮箱（选填，填写则同步创建管理员账号）')
                    ->visibleOn('create'),
                TextInput::make('admin_password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->rule(\Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers())
                    ->label('管理员初始密码（8 位以上，含大小写与数字）')
                    ->visibleOn('create'),
            ]);
    }
}
