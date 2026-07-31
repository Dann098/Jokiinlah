<?php

namespace App\Filament\Resources\Consultations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConsultationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengajuan')->schema([
                    TextEntry::make('request_code')->label('Kode'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('source')
                        ->label('Sumber')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => $state === 'customer_portal' ? 'Portal Customer' : 'Website Publik'),
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('email')->label('Email'),
                    TextEntry::make('phone')->label('WhatsApp'),
                    TextEntry::make('service.name')->label('Layanan')->placeholder('—'),
                    TextEntry::make('project_title')->label('Judul kebutuhan')->columnSpanFull(),
                    TextEntry::make('description')->label('Deskripsi')->columnSpanFull(),
                    TextEntry::make('deadline')
                        ->label('Deadline')
                        ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                        ->placeholder('—'),
                    TextEntry::make('technology')->label('Teknologi')->placeholder('—'),
                    TextEntry::make('budget')->label('Perkiraan anggaran')->money('IDR')->placeholder('—'),
                ])->columns(2),
                Section::make('Persetujuan dan dokumen')->schema([
                    IconEntry::make('privacy_accepted_at')
                        ->label('Privasi disetujui')
                        ->boolean()
                        ->getStateUsing(fn ($record): bool => $record->privacy_accepted_at !== null),
                    IconEntry::make('academic_integrity_accepted_at')
                        ->label('Integritas disetujui')
                        ->boolean()
                        ->getStateUsing(fn ($record): bool => $record->academic_integrity_accepted_at !== null),
                    TextEntry::make('privacy_policy_version')->label('Versi privasi')->placeholder('—'),
                    TextEntry::make('terms_version')->label('Versi ketentuan')->placeholder('—'),
                    TextEntry::make('attachment_original_name')->label('Lampiran')->placeholder('Tidak ada'),
                    TextEntry::make('attachment_size')
                        ->label('Ukuran lampiran')
                        ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1, ',', '.').' KB' : '—'),
                ])->columns(2),
                Section::make('Operasional')->schema([
                    TextEntry::make('user.name')->label('Customer terhubung')->placeholder('Belum terhubung'),
                    TextEntry::make('project.project_code')->label('Proyek hasil konversi')->placeholder('Belum dikonversi'),
                    TextEntry::make('admin_note')->label('Catatan admin')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('customer_response')->label('Tanggapan customer-facing')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('rejection_reason')->label('Alasan penolakan')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('created_at')
                        ->label('Diterima')
                        ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone')),
                ])->columns(2),
            ]);
    }
}
