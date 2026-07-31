<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Services\PortfolioImageStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PortfolioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')->required()->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('category')
                    ->label('Kategori')->required()->maxLength(50),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('problem')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('solution')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('result')
                    ->default(null)
                    ->columnSpanFull(),
                TagsInput::make('technologies')
                    ->label('Teknologi')
                    ->columnSpanFull(),
                self::imageUpload('thumbnail', 'Thumbnail portofolio', 'portfolios/thumbnails')
                    ->imagePreviewHeight('180')
                    ->itemPanelAspectRatio('16:9')
                    ->helperText('Gunakan gambar landscape dengan rasio 16:9. Format JPG, PNG, atau WebP, maksimal 4 MB.'),
                self::imageUpload('gallery', 'Galeri portofolio', 'portfolios/gallery')
                    ->multiple()
                    ->maxFiles(8)
                    ->reorderable()
                    ->appendFiles()
                    ->imagePreviewHeight('140')
                    ->helperText('Unggah maksimal 8 gambar. Urutan gambar dapat diubah dengan drag and drop.')
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Terbit')->default(false),
                Toggle::make('is_demo')
                    ->label('Data demo')->default(true)->required(),
            ]);
    }

    private static function imageUpload(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->rules(['image', 'extensions:jpg,jpeg,png,webp'])
            ->maxSize(4096)
            ->previewable()
            ->openable()
            ->downloadable()
            ->fetchFileInformation(false)
            ->preventFilePathTampering()
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.self::extensionForMime($file->getMimeType()),
            )
            ->getUploadedFileUsing(
                fn (string $file): ?array => app(PortfolioImageStorage::class)->uploadMetadata($file),
            )
            ->getOpenableFileUrlUsing(
                fn (string $file): ?string => app(PortfolioImageStorage::class)->url($file),
            )
            ->getDownloadableFileUrlUsing(
                fn (string $file): ?string => app(PortfolioImageStorage::class)->url($file),
            );
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \RuntimeException('Unsupported portfolio image type.'),
        };
    }
}
