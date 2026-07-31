<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\RemindersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Schemas\ProjectInfolist;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $modelLabel = 'proyek';

    protected static ?string $pluralModelLabel = 'proyek';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = true;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MilestonesRelationManager::class,
            FilesRelationManager::class,
            RevisionsRelationManager::class,
            RemindersRelationManager::class,
            AppointmentsRelationManager::class,
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['customer', 'assignedStaff', 'service']);
        $user = auth()->user();

        return $user ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['project_code', 'title', 'customer.name'];
    }
}
