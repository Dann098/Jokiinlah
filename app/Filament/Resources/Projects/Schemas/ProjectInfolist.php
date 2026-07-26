<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan proyek')->schema([
                TextEntry::make('project_code')->label('Kode')->copyable(),
                TextEntry::make('title')->label('Judul')->columnSpanFull(),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('assignedStaff.name')->label('Staff')->placeholder('Belum ditugaskan'),
                TextEntry::make('service.name')->label('Layanan'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('progress')->label('Progress')->suffix('%'),
                TextEntry::make('start_date')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->placeholder('—'),
                TextEntry::make('deadline')
                    ->label('Deadline')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->placeholder('—'),
                TextEntry::make('description')->label('Deskripsi')->columnSpanFull(),
            ])->columns(2),
            Section::make('Informasi admin')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                ->schema([
                    TextEntry::make('payment_status')->label('Pembayaran')->badge(),
                    TextEntry::make('payment_updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                        ->placeholder('—'),
                    TextEntry::make('payment_note')->label('Catatan pembayaran')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('admin_note')->label('Catatan internal')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('consultation.request_code')->label('Asal konsultasi')->placeholder('Proyek manual'),
                ])->columns(2),
        ]);
    }
}
