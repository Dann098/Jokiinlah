<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
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

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestone';

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
            Textarea::make('description')->label('Deskripsi untuk customer')->maxLength(4000)->columnSpanFull(),
            Textarea::make('internal_note')->label('Catatan internal')->maxLength(4000)->columnSpanFull(),
            DateTimePicker::make('due_date')->label('Tenggat')->timezone(config('jokiinlah.display_timezone')),
            Select::make('status')->label('Status')->options(MilestoneStatus::class)->default(MilestoneStatus::Pending->value)->required(),
            DateTimePicker::make('completed_at')->label('Diselesaikan')->timezone(config('jokiinlah.display_timezone')),
            TextInput::make('sort_order')->label('Urutan')->numeric()->minValue(0)->default(0)->required(),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Judul'),
            TextEntry::make('description')->label('Deskripsi untuk customer')->placeholder('-')->columnSpanFull(),
            TextEntry::make('internal_note')->label('Catatan internal')->placeholder('-')->columnSpanFull(),
            TextEntry::make('due_date')->label('Tenggat')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->placeholder('-'),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('completed_at')->label('Diselesaikan')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->placeholder('-'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('due_date')->label('Tenggat')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('sort_order')->label('Urutan')->numeric()->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah milestone')
                    ->modalHeading('Tambah milestone')
                    ->using(fn (array $data): ProjectMilestone => $this->getOwnerRecord()->milestones()->create($data)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ])
            ->toolbarActions([]);
    }
}
