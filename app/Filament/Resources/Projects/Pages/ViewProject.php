<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\AssignProjectStaff;
use App\Actions\Projects\UpdatePaymentStatus;
use App\Actions\Projects\UpdateProjectProgress;
use App\Actions\Projects\UpdateProjectStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('Ubah status')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    Select::make('status')
                        ->label('Status tujuan')
                        ->options(function (): array {
                            $user = auth()->user();

                            return $user?->isAdmin()
                                ? ProjectStatus::options()
                                : collect($this->record->status->normalTransitions())
                                    ->mapWithKeys(fn (ProjectStatus $status): array => [$status->value => $status->label()])
                                    ->all();
                        })
                        ->required(),
                    Textarea::make('override_reason')
                        ->label('Alasan override admin')
                        ->helperText('Wajib jika status tujuan bukan transisi normal.')
                        ->maxLength(1000)
                        ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                ])
                ->action(fn (array $data) => app(UpdateProjectStatus::class)->execute(
                    $this->record,
                    ProjectStatus::from($data['status']),
                    auth()->user(),
                    $data['override_reason'] ?? null,
                )),
            Action::make('changeProgress')
                ->label('Ubah progress')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    TextInput::make('progress')
                        ->label('Progress (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(fn (): int => $this->record->progress)
                        ->required(),
                ])
                ->action(fn (array $data) => app(UpdateProjectProgress::class)->execute(
                    $this->record,
                    (int) $data['progress'],
                    auth()->user(),
                )),
            Action::make('assignStaff')
                ->label('Atur staff')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    Select::make('assigned_staff_id')
                        ->label('Staff aktif')
                        ->options(fn (): array => User::query()
                            ->where('role', UserRole::Staff->value)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->default(fn () => $this->record->assigned_staff_id),
                ])
                ->action(fn (array $data) => app(AssignProjectStaff::class)->execute(
                    $this->record,
                    filled($data['assigned_staff_id'] ?? null) ? User::query()->findOrFail($data['assigned_staff_id']) : null,
                    auth()->user(),
                ))
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            Action::make('payment')
                ->label('Pembayaran')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->schema([
                    Select::make('payment_status')
                        ->label('Status pembayaran manual')
                        ->options(PaymentStatus::class)
                        ->default(fn () => $this->record->payment_status)
                        ->required(),
                    Textarea::make('payment_note')
                        ->label('Catatan internal')
                        ->maxLength(2000)
                        ->default(fn () => $this->record->payment_note),
                ])
                ->action(fn (array $data) => app(UpdatePaymentStatus::class)->execute(
                    $this->record,
                    PaymentStatus::from($data['payment_status']),
                    $data['payment_note'] ?? null,
                    auth()->user(),
                ))
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            EditAction::make()
                ->label('Edit detail')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
        ];
    }
}
