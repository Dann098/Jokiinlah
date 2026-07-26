<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Project;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Jadwal';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project
            && auth()->user()?->can('view', $ownerRecord) === true
            && (auth()->user()->isAdmin() || $ownerRecord->assigned_staff_id === auth()->id());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Judul')->required()->maxLength(255),
            DateTimePicker::make('appointment_date')->label('Waktu')->timezone(config('jokiinlah.display_timezone'))->required(),
            TextInput::make('meeting_link')->label('Tautan rapat HTTPS')->url()->startsWith('https://')->maxLength(2048),
            Textarea::make('notes')->label('Catatan untuk customer')->maxLength(4000)->columnSpanFull(),
            Textarea::make('internal_note')->label('Catatan internal')->maxLength(4000)->columnSpanFull(),
            Select::make('status')->label('Status')->options(AppointmentStatus::class)->default(AppointmentStatus::Scheduled->value)->required(),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Judul'),
            TextEntry::make('appointment_date')->label('Waktu')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('meeting_link')->label('Tautan rapat')->url(fn (Appointment $record): ?string => $record->safeMeetingUrl())->openUrlInNewTab()->placeholder('-'),
            TextEntry::make('notes')->label('Catatan untuk customer')->placeholder('-')->columnSpanFull(),
            TextEntry::make('internal_note')->label('Catatan internal')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('appointment_date')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('appointment_date')->label('Waktu')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('meeting_link')->label('Rapat')->url(fn (Appointment $record): ?string => $record->safeMeetingUrl())->openUrlInNewTab()->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah jadwal')
                    ->modalHeading('Tambah jadwal')
                    ->using(fn (array $data): Appointment => Appointment::query()->forceCreate(array_merge($data, [
                        'project_id' => $this->getOwnerRecord()->id,
                        'customer_id' => $this->getOwnerRecord()->customer_id,
                        'staff_id' => $this->getOwnerRecord()->assigned_staff_id,
                    ]))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([]);
    }
}
