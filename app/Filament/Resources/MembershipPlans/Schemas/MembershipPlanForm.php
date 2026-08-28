<?php

namespace App\Filament\Resources\MembershipPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MembershipPlanForm
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
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('original_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('CNY'),
                Select::make('billing_cycle')
                    ->options(['lifetime'=>'终身','monthly'=>'月付','quarterly'=>'季付','yearly'=>'年付','weekly'=>'周付','daily'=>'日付','fixed'=>'固定天数'])
                    ->required()->default('monthly')->label('计费周期'),
                TextInput::make('duration_days')
                    ->numeric()->label('固定天数'),
                TextInput::make('trial_days')
                    ->required()->numeric()->default(0)->label('试用天数'),
                Textarea::make('benefits')
                    ->columnSpanFull()->label('权益说明'),
                Toggle::make('is_recommended')->label('推荐'),
                Toggle::make('is_active')->label('启用'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
