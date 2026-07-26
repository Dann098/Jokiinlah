<?php

namespace App\Filament\Resources\Staffs\Pages;

use App\Filament\Resources\Staffs\StaffResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStaff extends ViewRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Edit staff')];
    }
}
