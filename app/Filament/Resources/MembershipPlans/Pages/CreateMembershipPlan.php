<?php

namespace App\Filament\Resources\MembershipPlans\Pages;

use App\Filament\Resources\MembershipPlans\MembershipPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipPlan extends CreateRecord
{
    protected static string $resource = MembershipPlanResource::class;
}
