<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Actions\Revisions\UpdateRevision;
use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Project;
use App\Models\Revision;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisi Customer';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project
            && auth()->user()?->can('view', $ownerRecord) === true
            && (auth()->user()->isAdmin() || $ownerRecord->assigned_staff_id === auth()->id());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label('Status')->options(RevisionStatus::class)->required(),
            Select::make('priority')->label('Prioritas')->options(RevisionPriority::class)->required(),
            Textarea::make('admin_response')->label('Respons untuk customer')->maxLength(5000)->columnSpanFull(),
            Textarea::make('internal_note')->label('Catatan internal')->maxLength(5000)->columnSpanFull(),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Judul'),
            TextEntry::make('submitter.name')->label('Pengaju'),
            TextEntry::make('description')->label('Permintaan')->columnSpanFull(),
            TextEntry::make('section_reference')->label('Bagian')->placeholder('-'),
            TextEntry::make('priority')->label('Prioritas')->badge(),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('admin_response')->label('Respons untuk customer')->placeholder('-')->columnSpanFull(),
            TextEntry::make('internal_note')->label('Catatan internal')->placeholder('-')->columnSpanFull(),
            TextEntry::make('attachment_original_name')->label('Lampiran')->placeholder('-'),
            TextEntry::make('attachment_size')->label('Ukuran lampiran')->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1).' KB' : '-'),
            TextEntry::make('created_at')->label('Diajukan')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable(),
                TextColumn::make('submitter.name')->label('Pengaju'),
                TextColumn::make('priority')->label('Prioritas')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->sortable(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->label('Tanggapi')
                    ->modalHeading('Tanggapi revisi customer')
                    ->using(fn (Revision $record, array $data): Revision => app(UpdateRevision::class)->execute($record, $data, auth()->user())),
                Action::make('downloadAttachment')
                    ->label('Unduh lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (Revision $record): bool => filled($record->attachment_path))
                    ->url(fn (Revision $record): string => route('admin.revisions.attachment', $record)),
            ])
            ->toolbarActions([]);
    }
}
