<?php

namespace App\Filament\Resources\MemberProfiles;

use App\Filament\Resources\MemberProfiles\Pages\CreateMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\MemberProfiles\Tables\MemberProfilesTable;
use App\Models\MemberProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberProfileResource extends Resource
{
    protected static ?string $model = MemberProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = '会员运营';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '会员';

    protected static ?string $pluralModelLabel = '会员列表';

    protected static ?string $recordTitleAttribute = 'member_no';

    public static function form(Schema $schema): Schema
    {
        return MemberProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProfilesTable::configure($table);
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
            'index' => ListMemberProfiles::route('/'),
            'create' => CreateMemberProfile::route('/create'),
            'edit' => EditMemberProfile::route('/{record}/edit'),
        ];
    }
}
