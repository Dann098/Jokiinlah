<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Filament\Forms\PublicImageUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                PublicImageUpload::make('thumbnail', 'Thumbnail portofolio', 'portfolios/thumbnails')
                    ->imagePreviewHeight('180')
                    ->itemPanelAspectRatio('16:9')
                    ->helperText('Gunakan gambar landscape dengan rasio 16:9. Format JPG, PNG, atau WebP, maksimal 4 MB.'),
                PublicImageUpload::make('gallery', 'Galeri portofolio', 'portfolios/gallery')
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
}
