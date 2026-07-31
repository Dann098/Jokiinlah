<?php

namespace App\Filament\Resources\Consultations\Pages;

use App\Actions\Consultations\ApproveCustomerProjectRequest;
use App\Actions\Consultations\ConvertConsultationToProject;
use App\Actions\Consultations\LinkConsultationToCustomer;
use App\Actions\Consultations\RejectCustomerProjectRequest;
use App\Actions\Consultations\RequestCustomerProjectInformation;
use App\Enums\ConsultationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Consultations\ConsultationResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewConsultation extends ViewRecord
{
    protected static string $resource = ConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadAttachment')
                ->label('Unduh lampiran')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('admin.consultations.attachment', $this->record))
                ->visible(fn (): bool => filled($this->record->attachment_path)),
            Action::make('linkCustomer')
                ->label('Hubungkan customer')
                ->icon('heroicon-o-link')
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer terverifikasi dengan email sama')
                        ->options(fn (): array => User::query()
                            ->where('role', UserRole::Customer->value)
                            ->where('is_active', true)
                            ->whereNotNull('email_verified_at')
                            ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->record->email)])
                            ->pluck('name', 'id')
                            ->all())
                        ->required(),
                ])
                ->action(fn (array $data) => app(LinkConsultationToCustomer::class)->execute(
                    $this->record,
                    User::query()->findOrFail($data['customer_id']),
                    auth()->user(),
                ))
                ->visible(fn (): bool => $this->record->user_id === null),
            Action::make('requestInformation')
                ->label('Minta info tambahan')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('warning')
                ->schema([
                    Textarea::make('customer_response')
                        ->label('Informasi yang perlu dilengkapi')
                        ->required()
                        ->minLength(10)
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    app(RequestCustomerProjectInformation::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['customer_response'],
                    );
                    $this->refreshFormData(['status', 'customer_response', 'responded_at']);
                })
                ->visible(fn (): bool => $this->record->source === 'customer_portal'
                    && in_array($this->record->status, [ConsultationStatus::New, ConsultationStatus::Contacted], true)),
            Action::make('approveRequest')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->schema([
                    Textarea::make('customer_response')
                        ->label('Tanggapan untuk customer')
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    app(ApproveCustomerProjectRequest::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['customer_response'] ?? null,
                    );
                    $this->refreshFormData(['status', 'customer_response', 'responded_at']);
                })
                ->visible(fn (): bool => $this->record->source === 'customer_portal'
                    && in_array($this->record->status, [ConsultationStatus::New, ConsultationStatus::Contacted], true)),
            Action::make('rejectRequest')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label('Alasan penolakan')
                        ->required()
                        ->minLength(10)
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    app(RejectCustomerProjectRequest::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['rejection_reason'],
                    );
                    $this->refreshFormData(['status', 'rejection_reason', 'responded_at']);
                })
                ->visible(fn (): bool => $this->record->source === 'customer_portal'
                    && in_array($this->record->status, [
                        ConsultationStatus::New,
                        ConsultationStatus::Contacted,
                        ConsultationStatus::Reviewed,
                    ], true)),
            Action::make('convert')
                ->label('Konversi ke proyek')
                ->color('success')
                ->icon('heroicon-o-arrow-right-circle')
                ->requiresConfirmation()
                ->schema([
                    TextInput::make('title')
                        ->label('Judul proyek')
                        ->default(fn (): string => $this->record->project_title)
                        ->required()
                        ->maxLength(255),
                    Select::make('assigned_staff_id')
                        ->label('Staff')
                        ->options(fn (): array => User::query()
                            ->where('role', UserRole::Staff->value)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable(),
                    DateTimePicker::make('deadline')
                        ->label('Deadline')
                        ->timezone(config('jokiinlah.display_timezone'))
                        ->default(fn () => $this->record->deadline),
                ])
                ->action(function (array $data): void {
                    $project = app(ConvertConsultationToProject::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data,
                    );
                    $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                })
                ->visible(fn (): bool => $this->record->status === ConsultationStatus::Reviewed
                    && $this->record->user_id !== null
                    && ! $this->record->project()->exists()),
            EditAction::make()->label('Tindak lanjut'),
        ];
    }
}
