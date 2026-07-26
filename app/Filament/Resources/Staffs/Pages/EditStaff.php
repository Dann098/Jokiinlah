<?php

namespace App\Filament\Resources\Staffs\Pages;

use App\Actions\Users\UpdateManagedUser;
use App\Enums\UserRole;
use App\Filament\Resources\Staffs\StaffResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateManagedUser::class)->execute($record, $data, UserRole::Staff, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Lihat')];
    }
}
