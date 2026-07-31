<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleCategory;
use App\Filament\Forms\PublicImageUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')->required()->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true),
                Textarea::make('excerpt')
                    ->label('Ringkasan')->required()->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->label('Isi')->required()->maxLength(50000)
                    ->columnSpanFull(),
                Select::make('category')
                    ->options(ArticleCategory::class)
                    ->required(),
                PublicImageUpload::make('thumbnail', 'Thumbnail artikel', 'articles/thumbnails')
                    ->imagePreviewHeight('180')
                    ->itemPanelAspectRatio('16:9')
                    ->helperText('Gunakan gambar landscape. Format JPG, PNG, atau WebP, maksimal 4 MB.'),
                Toggle::make('is_published')
                    ->label('Terbit')->default(false),
                DateTimePicker::make('published_at')->label('Waktu terbit')->timezone(config('jokiinlah.display_timezone')),
            ]);
    }
}
