<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Appointment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingOperations extends TableWidget
{
    protected static ?string $heading = 'Jadwal Operasional Mendatang';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                return Appointment::query()
                    ->with(['project', 'customer', 'staff'])
                    ->where('appointment_date', '>=', now())
                    ->when($user?->isStaff(), fn (Builder $query): Builder => $query
                        ->whereHas('project', fn (Builder $project): Builder => $project->where('assigned_staff_id', $user->id)))
                    ->orderBy('appointment_date')
                    ->limit(5);
            })
            ->columns([
                TextColumn::make('appointment_date')->label('Waktu')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
                TextColumn::make('title')->label('Agenda')->searchable(),
                TextColumn::make('project.project_code')->label('Proyek'),
                TextColumn::make('customer.name')->label('Customer')->visible(fn (): bool => auth()->user()?->isAdmin() === true),
                TextColumn::make('staff.name')->label('Staff')->visible(fn (): bool => auth()->user()?->isAdmin() === true)->placeholder('Belum ditugaskan'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->recordUrl(fn (Appointment $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id]))
            ->paginated(false)
            ->toolbarActions([]);
    }
}
