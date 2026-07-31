<?php

namespace App\Filament\Resources\Consultations\Schemas;

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
                    ->description('Identitas, status, dan tanggapan customer-facing bersifat read-only. Gunakan aksi pada halaman detail untuk mengubah workflow.')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Catatan admin')
                            ->maxLength(3000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
