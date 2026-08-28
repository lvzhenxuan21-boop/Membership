<?php

namespace App\Filament\Resources\CheckIns\Pages;

use App\Filament\Resources\CheckIns\CheckInResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckIn extends CreateRecord
{
    protected static string $resource = CheckInResource::class;
}
