<?php

namespace App\Filament\Resources\MembershipLevels;

use App\Filament\Resources\MembershipLevels\Pages\CreateMembershipLevel;
use App\Filament\Resources\MembershipLevels\Pages\EditMembershipLevel;
use App\Filament\Resources\MembershipLevels\Pages\ListMembershipLevels;
use App\Filament\Resources\MembershipLevels\Schemas\MembershipLevelForm;
use App\Filament\Resources\MembershipLevels\Tables\MembershipLevelsTable;
use App\Models\MembershipLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MembershipLevelResource extends Resource
{
    protected static ?string $model = MembershipLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|\UnitEnum|null $navigationGroup = '会员配置';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '会员等级';

    protected static ?string $pluralModelLabel = '等级管理';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MembershipLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipLevels::route('/'),
            'create' => CreateMembershipLevel::route('/create'),
            'edit' => EditMembershipLevel::route('/{record}/edit'),
        ];
    }
}
