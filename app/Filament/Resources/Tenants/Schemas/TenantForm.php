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
            ]);
    }
}
