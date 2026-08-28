<?php

namespace App\Filament\Resources\CheckIns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CheckInForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_id')
                    ->required()
                    ->numeric(),
                Select::make('branch_id')
                    ->relationship('branch', 'name'),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                DateTimePicker::make('checked_in_at')
                    ->required(),
                TextInput::make('method')
                    ->required()
                    ->default('qrcode'),
            ]);
    }
}
