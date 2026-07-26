<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi customer')->schema([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('email')->label('Email'),
                TextEntry::make('phone')->label('Nomor WhatsApp')->placeholder('—'),
                TextEntry::make('institution')->label('Institusi')->placeholder('—'),
                TextEntry::make('study_program')->label('Program studi')->placeholder('—'),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
                IconEntry::make('email_verified_at')
                    ->label('Email terverifikasi')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->hasVerifiedEmail()),
                TextEntry::make('ownedProjects.project_code')
                    ->label('Proyek terkait')
                    ->bulleted()
                    ->listWithLineBreaks()
                    ->placeholder('Belum memiliki proyek'),
                TextEntry::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
            ])->columns(2),
        ]);
    }
}
