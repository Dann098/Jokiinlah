<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\ServiceCategory;
use App\Filament\Forms\PublicImageUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')->required()->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('category')
                    ->options(ServiceCategory::class)
                    ->required(),
                Textarea::make('short_description')
                    ->label('Ringkasan')->required()->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi')->required()->maxLength(20000)
                    ->columnSpanFull(),
                TagsInput::make('features')
                    ->label('Fitur')
                    ->columnSpanFull(),
                TagsInput::make('technologies')
                    ->label('Teknologi')
                    ->columnSpanFull(),
                TextInput::make('icon')
                    ->label('Nama ikon')->maxLength(100),
                PublicImageUpload::make('image', 'Gambar layanan', 'services/images')
                    ->imagePreviewHeight('180')
                    ->itemPanelAspectRatio('16:9')
                    ->helperText('Gunakan gambar landscape. Format JPG, PNG, atau WebP, maksimal 4 MB.'),
                Toggle::make('is_active')
                    ->label('Aktif')->default(true),
                TextInput::make('sort_order')
                    ->label('Urutan')->required()->minValue(0)
                    ->numeric()
                    ->default(0),
            ]);
    }
}
