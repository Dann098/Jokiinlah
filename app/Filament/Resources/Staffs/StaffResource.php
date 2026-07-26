<?php

namespace App\Filament\Resources\Staffs;

use App\Enums\UserRole;
use App\Filament\Resources\Staffs\Pages\CreateStaff;
use App\Filament\Resources\Staffs\Pages\EditStaff;
use App\Filament\Resources\Staffs\Pages\ListStaff;
use App\Filament\Resources\Staffs\Pages\ViewStaff;
use App\Filament\Resources\Staffs\Schemas\StaffForm;
use App\Filament\Resources\Staffs\Schemas\StaffInfolist;
use App\Filament\Resources\Staffs\Tables\StaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static ?string $modelLabel = 'staff';

    protected static ?string $pluralModelLabel = 'staff';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StaffInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Staff->value);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'view' => ViewStaff::route('/{record}'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
