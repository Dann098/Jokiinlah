<?php

namespace App\Filament\Resources\Portfolios\Schemas;

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
                TextInput::make('thumbnail')
                    ->label('Path thumbnail publik')->maxLength(255)->notRegex('/\.\./')->regex('/^(images|storage)\/[A-Za-z0-9_\/.\-]+$/'),
                TagsInput::make('gallery')
                    ->label('Path galeri publik')
                    ->nestedRecursiveRules(['max:255', 'not_regex:/\.\./', 'regex:/^(images|storage)\/[A-Za-z0-9_\/.\-]+$/'])
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Terbit')->default(false),
                Toggle::make('is_demo')
                    ->label('Data demo')->default(true)->required(),
            ]);
    }
}
