<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Actions\ProjectFiles\CreateProjectFileRecord;
use App\Actions\ProjectFiles\CreateProjectFileVersion;
use App\Exceptions\UnsafeUploadException;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Rules\SafePrivateUpload;
use App\Services\PrivateProjectFileStorage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Berkas Privat';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project
            && auth()->user()?->can('view', $ownerRecord) === true
            && (auth()->user()->isAdmin() || $ownerRecord->assigned_staff_id === auth()->id());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->uploadFields());
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('original_name')->label('Nama berkas'),
            TextEntry::make('version')->label('Versi')->numeric(),
            TextEntry::make('category')->label('Kategori')->formatStateUsing(fn (string $state): string => config("jokiinlah.project_file_categories.{$state}", $state)),
            TextEntry::make('uploader.name')->label('Pengunggah'),
            TextEntry::make('file_type')->label('Tipe berkas'),
            TextEntry::make('file_size')->label('Ukuran')->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),
            TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
            TextEntry::make('created_at')->label('Diunggah')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_name')->label('Nama berkas')->searchable(),
                TextColumn::make('version')->label('Versi')->numeric()->sortable(),
                TextColumn::make('category')->label('Kategori')->formatStateUsing(fn (string $state): string => config("jokiinlah.project_file_categories.{$state}", $state)),
                TextColumn::make('uploader.name')->label('Pengunggah'),
                TextColumn::make('file_size')->label('Ukuran')->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah berkas')
                    ->modalHeading('Unggah berkas privat')
                    ->authorize(fn (): bool => auth()->user()?->can('create', [ProjectFile::class, $this->getOwnerRecord()]) === true)
                    ->using(fn (array $data): ProjectFile => $this->storeNewFile($data)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ProjectFile $record): string => route('project-files.download', $record)),
                Action::make('uploadVersion')
                    ->label('Versi baru')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->authorize(fn (ProjectFile $record): bool => auth()->user()?->can('uploadVersion', $record) === true)
                    ->schema($this->uploadFields())
                    ->action(fn (ProjectFile $record, array $data): ProjectFile => $this->storeVersion($record, $data)),
            ])
            ->toolbarActions([]);
    }

    private function uploadFields(): array
    {
        return [
            FileUpload::make('file')
                ->label('Berkas')
                ->storeFiles(false)
                ->rules([
                    File::types(config('jokiinlah.allowed_file_extensions'))->max((int) config('jokiinlah.upload_max_size')),
                    new SafePrivateUpload,
                ])
                ->required(),
            Select::make('category')->label('Kategori')->options(config('jokiinlah.project_file_categories'))->required(),
            Textarea::make('description')->label('Deskripsi')->maxLength(2000)->columnSpanFull(),
        ];
    }

    private function storeNewFile(array $data): ProjectFile
    {
        abort_unless(auth()->user()?->can('create', [ProjectFile::class, $this->getOwnerRecord()]), 403);

        return $this->persistUploadedFile(
            $data,
            fn (array $metadata): ProjectFile => app(CreateProjectFileRecord::class)
                ->execute($this->getOwnerRecord(), auth()->user(), $metadata),
        );
    }

    private function storeVersion(ProjectFile $record, array $data): ProjectFile
    {
        abort_unless(auth()->user()?->can('uploadVersion', $record), 403);

        return $this->persistUploadedFile(
            $data,
            fn (array $metadata): ProjectFile => app(CreateProjectFileVersion::class)
                ->execute($record, auth()->user(), $metadata),
        );
    }

    private function persistUploadedFile(array $data, callable $persist): ProjectFile
    {
        /** @var UploadedFile $upload */
        $upload = $data['file'];
        $storage = app(PrivateProjectFileStorage::class);
        try {
            $metadata = $storage->store($upload);
        } catch (UnsafeUploadException $exception) {
            Notification::make()->danger()->title('Berkas ditolak')->body($exception->getMessage())->send();

            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        try {
            $file = $persist(array_merge($metadata, [
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
            ]));
        } catch (Throwable $exception) {
            $storage->delete($metadata['file_path']);
            throw $exception;
        }

        Notification::make()->success()->title('Berkas privat tersimpan')->send();

        return $file;
    }
}
