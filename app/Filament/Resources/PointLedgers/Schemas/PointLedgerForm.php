<?php

namespace App\Filament\Resources\PointLedgers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PointLedgerForm
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
                TextInput::make('type')
                    ->required(),
                TextInput::make('points')
                    ->required()
                    ->numeric(),
                TextInput::make('balance_after')
                    ->required()
                    ->numeric(),
                TextInput::make('source_type'),
                TextInput::make('source_id')
                    ->numeric(),
                TextInput::make('description'),
                TextInput::make('operator'),
                DateTimePicker::make('expired_at'),
            ]);
    }
}
