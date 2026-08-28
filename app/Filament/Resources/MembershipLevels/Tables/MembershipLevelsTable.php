<?php

namespace App\Filament\Resources\MembershipLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_points')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_growth')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discount_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('points_multiplier')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('badge_color')
                    ->searchable(),
                IconColumn::make('is_default')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
