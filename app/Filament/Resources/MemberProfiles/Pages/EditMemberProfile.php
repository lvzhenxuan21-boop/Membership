<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMemberProfile extends EditRecord
{
    protected static string $resource = MemberProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
