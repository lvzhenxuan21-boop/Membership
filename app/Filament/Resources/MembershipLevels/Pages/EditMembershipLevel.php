<?php

namespace App\Filament\Resources\MembershipLevels\Pages;

use App\Filament\Resources\MembershipLevels\MembershipLevelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMembershipLevel extends EditRecord
{
    protected static string $resource = MembershipLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
