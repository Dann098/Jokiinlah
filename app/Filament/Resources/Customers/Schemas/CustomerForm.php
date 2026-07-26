<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas customer')
                ->description('Role dan status verifikasi ditentukan server dan tidak tersedia pada formulir.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama lengkap')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('phone')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        ->maxLength(30)
                        ->regex('/^\+?[0-9\s-]{8,30}$/'),
                    TextInput::make('institution')
                        ->label('Institusi')
                        ->maxLength(255),
                    TextInput::make('study_program')
                        ->label('Program studi')
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Akun aktif')
                        ->default(true)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}
