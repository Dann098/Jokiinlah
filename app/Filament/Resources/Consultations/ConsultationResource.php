<?php

namespace App\Filament\Resources\Consultations;

use App\Filament\Resources\Consultations\Pages\EditConsultation;
use App\Filament\Resources\Consultations\Pages\ListConsultations;
use App\Filament\Resources\Consultations\Pages\ViewConsultation;
use App\Filament\Resources\Consultations\Schemas\ConsultationForm;
use App\Filament\Resources\Consultations\Schemas\ConsultationInfolist;
use App\Filament\Resources\Consultations\Tables\ConsultationsTable;
use App\Models\Consultation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static ?string $modelLabel = 'konsultasi';

    protected static ?string $pluralModelLabel = 'konsultasi';

    protected static ?string $recordTitleAttribute = 'request_code';

    protected static bool $isGloballySearchable = true;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        return ConsultationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConsultationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsultationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultations::route('/'),
            'view' => ViewConsultation::route('/{record}'),
            'edit' => EditConsultation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['service', 'user', 'project']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['request_code', 'name', 'email', 'phone', 'project_title'];
    }
}
