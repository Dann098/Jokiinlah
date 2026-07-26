<?php

namespace App\Filament\Resources\Staffs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas staff')
                ->description('Role staff ditentukan server. Password awal acak dan tautan pengaturan password dikirim melalui broker.')
                ->schema([
                    TextInput::make('name')->label('Nama lengkap')->required()->maxLength(120),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('phone')->label('Nomor WhatsApp')->tel()->maxLength(30)->regex('/^\+?[0-9\s-]{8,30}$/'),
                    Toggle::make('is_active')->label('Akun aktif')->default(true)->required(),
                ])
                ->columns(2),
        ]);
    }
}
