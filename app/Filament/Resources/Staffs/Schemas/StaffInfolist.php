<?php

namespace App\Filament\Resources\Staffs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi staff')->schema([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('email')->label('Email'),
                TextEntry::make('phone')->label('Nomor WhatsApp')->placeholder('—'),
                IconEntry::make('is_active')->label('Aktif')->boolean(),
                IconEntry::make('email_verified_at')
                    ->label('Email terverifikasi')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->hasVerifiedEmail()),
                TextEntry::make('assignedProjects.project_code')
                    ->label('Proyek ditugaskan')
                    ->bulleted()
                    ->listWithLineBreaks()
                    ->placeholder('Belum ada penugasan'),
                TextEntry::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
            ])->columns(2),
        ]);
    }
}
