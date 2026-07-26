<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\UpdateProjectDetails;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateProjectDetails::class)->execute($record, $data, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Kembali ke proyek')];
    }
}
