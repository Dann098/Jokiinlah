<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\Reminder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'Pengingat';

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
            Textarea::make('description')->label('Deskripsi')->maxLength(4000)->columnSpanFull(),
            DateTimePicker::make('reminder_date')->label('Waktu pengingat')->timezone(config('jokiinlah.display_timezone'))->required(),
            Toggle::make('is_completed')->label('Sudah selesai')->default(false),
            Toggle::make('is_customer_visible')->label('Tampilkan kepada customer')->default(true),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Judul'),
            TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
            TextEntry::make('reminder_date')->label('Waktu')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
            IconEntry::make('is_completed')->label('Selesai')->boolean(),
            IconEntry::make('is_customer_visible')->label('Terlihat customer')->boolean(),
            TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('-'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('reminder_date')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('reminder_date')->label('Waktu')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->sortable(),
                IconColumn::make('is_completed')->label('Selesai')->boolean(),
                IconColumn::make('is_customer_visible')->label('Customer')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah pengingat')
                    ->modalHeading('Tambah pengingat')
                    ->using(fn (array $data): Reminder => Reminder::query()->forceCreate(array_merge($data, [
                        'project_id' => $this->getOwnerRecord()->id,
                        'user_id' => $this->getOwnerRecord()->customer_id,
                        'created_by' => auth()->id(),
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
