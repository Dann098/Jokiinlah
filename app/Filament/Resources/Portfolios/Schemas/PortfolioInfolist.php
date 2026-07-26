<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use Filament\Infolists\Components\IconEntry;
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
                TextEntry::make('thumbnail')
                    ->placeholder('-'),
                TextEntry::make('gallery')
                    ->placeholder('-')
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
