<?php

namespace App\Filament\Resources\Staffs\Pages;

use App\Actions\Users\CreateManagedUser;
use App\Enums\UserRole;
use App\Filament\Resources\Staffs\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateManagedUser::class)->execute($data, UserRole::Staff, auth()->user());
    }
}
