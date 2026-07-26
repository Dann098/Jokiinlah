<?php

namespace App\Filament\Resources\Consultations\Schemas;

use App\Enums\ConsultationStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConsultationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tindak lanjut konsultasi')
                    ->description('Identitas dan isi pengajuan bersifat read-only. Gunakan aksi terpisah untuk linking dan konversi.')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(ConsultationStatus::class)
                            ->required(),
                        Textarea::make('admin_note')
                            ->label('Catatan admin')
                            ->maxLength(3000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
