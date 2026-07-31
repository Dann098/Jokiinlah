<?php

namespace App\Filament\Forms;

use App\Services\PublicImageStorage;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class PublicImageUpload
{
    public static function make(string $name, string $label, string $directory): FileUpload
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
                fn (string $file): ?array => app(PublicImageStorage::class)->uploadMetadata($file),
            )
            ->getOpenableFileUrlUsing(
                fn (string $file): ?string => app(PublicImageStorage::class)->url($file),
            )
            ->getDownloadableFileUrlUsing(
                fn (string $file): ?string => app(PublicImageStorage::class)->url($file),
            );
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported public image type.'),
        };
    }
}
