<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Models\Portfolio;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PortfolioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('slug'),
                TextEntry::make('category'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('problem')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('solution')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('result')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('technologies')
                    ->placeholder('-')
                    ->columnSpanFull(),
                ImageEntry::make('thumbnail')
                    ->label('Thumbnail')
                    ->state(fn (Portfolio $record): string => $record->thumbnailUrl())
                    ->disk('public')
                    ->visibility('public')
                    ->height(180)
                    ->extraImgAttributes(['class' => 'rounded-xl object-cover']),
                TextEntry::make('gallery_count')
                    ->label('Jumlah gambar galeri')
                    ->state(fn (Portfolio $record): int => count($record->gallery ?? []))
                    ->columnSpanFull(),
                IconEntry::make('is_published')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
