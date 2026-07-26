<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('WhatsApp')->searchable()->placeholder('—'),
                TextColumn::make('owned_projects_count')
                    ->label('Proyek')
                    ->counts('ownedProjects')
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->hasVerifiedEmail()),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status akun')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
                TernaryFilter::make('email_verified_at')
                    ->label('Verifikasi email')
                    ->trueLabel('Terverifikasi')
                    ->falseLabel('Belum terverifikasi')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Edit'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada customer')
            ->emptyStateDescription('Customer yang dibuat atau mendaftar akan tampil di sini.');
    }
}
