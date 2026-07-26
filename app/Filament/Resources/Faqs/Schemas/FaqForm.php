<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_id')
                    ->label('Layanan')
                    ->relationship('service', 'name')
                    ->searchable()->preload(),
                TextInput::make('question')
                    ->label('Pertanyaan')->required()->maxLength(255),
                Textarea::make('answer')
                    ->label('Jawaban')->required()->maxLength(10000)
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->label('Kategori')->maxLength(100),
                TextInput::make('sort_order')
                    ->label('Urutan')->required()->minValue(0)
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')->default(true),
            ]);
    }
}
