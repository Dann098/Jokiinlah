<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Users\CreateManagedUser;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateManagedUser::class)->execute($data, UserRole::Customer, auth()->user());
    }
}
