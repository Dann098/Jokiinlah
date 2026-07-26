<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Actions\SiteSettings\UpdateSiteSetting;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateSiteSetting::class)->execute($record, $data['value'] ?? null, auth()->user());
    }
}
