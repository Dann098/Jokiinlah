<?php

namespace App\Filament\Resources\Consultations\Tables;

use App\Enums\ConsultationStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConsultationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('project_title')
                    ->label('Kebutuhan')
                    ->searchable()
                    ->wrap()
                    ->limit(55),
                TextColumn::make('service.name')
                    ->label('Layanan')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'customer_portal' ? 'Portal Customer' : 'Website Publik'),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->dateTime('d M Y', timezone: config('jokiinlah.display_timezone'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ConsultationStatus::class),
                SelectFilter::make('service_id')->label('Layanan')->relationship('service', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Tindak lanjut'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada konsultasi')
            ->emptyStateDescription('Pengajuan baru dari website publik akan tampil di sini.');
    }
}
