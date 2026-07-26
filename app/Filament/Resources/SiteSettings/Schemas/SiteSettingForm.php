<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('value')
                    ->label('Nilai')
                    ->required()
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }
}
