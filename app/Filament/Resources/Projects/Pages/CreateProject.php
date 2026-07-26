<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\CreateManualProject;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateManualProject::class)->execute($data, auth()->user());
    }
}
