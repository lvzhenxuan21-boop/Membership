<?php

namespace App\Filament\Resources\MembershipLevels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MembershipLevelForm
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
                TextInput::make('level')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('min_points')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('min_growth')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('discount_rate')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('points_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1),
                Textarea::make('benefits')
                    ->columnSpanFull(),
                TextInput::make('badge_color')
                    ->required()
                    ->default('#999999'),
                Toggle::make('is_default')
                    ->required(),
            ]);
    }
}
