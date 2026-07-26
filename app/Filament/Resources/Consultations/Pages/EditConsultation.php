<?php

namespace App\Filament\Resources\Consultations\Pages;

use App\Actions\Consultations\UpdateConsultation;
use App\Enums\ConsultationStatus;
use App\Filament\Resources\Consultations\ConsultationResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditConsultation extends EditRecord
{
    protected static string $resource = ConsultationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateConsultation::class)->execute(
            $record,
            ConsultationStatus::from($data['status']),
            $data['admin_note'] ?? null,
            auth()->user(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Lihat detail'),
        ];
    }
}
