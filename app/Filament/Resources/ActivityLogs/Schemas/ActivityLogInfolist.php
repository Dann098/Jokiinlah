<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('action'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('model_type')
                    ->placeholder('-'),
                TextEntry::make('model_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('metadata')
                    ->label('Metadata audit')
                    ->formatStateUsing(fn (?array $state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime('d M Y H:i:s', timezone: config('jokiinlah.display_timezone')),
            ]);
    }
}
