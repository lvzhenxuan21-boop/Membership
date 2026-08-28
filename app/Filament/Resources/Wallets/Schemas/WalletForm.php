<?php

namespace App\Filament\Resources\Wallets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WalletForm
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
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('frozen')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_recharged')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_consumed')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
