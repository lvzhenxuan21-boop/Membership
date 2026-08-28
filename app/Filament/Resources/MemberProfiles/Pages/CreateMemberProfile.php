<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMemberProfile extends CreateRecord
{
    protected static string $resource = MemberProfileResource::class;
}
