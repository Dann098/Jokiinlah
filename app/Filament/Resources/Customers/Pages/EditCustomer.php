<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Users\UpdateManagedUser;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateManagedUser::class)->execute($record, $data, UserRole::Customer, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Lihat')];
    }
}
