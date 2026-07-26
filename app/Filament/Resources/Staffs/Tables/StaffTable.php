<?php

namespace App\Filament\Resources\Staffs\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('WhatsApp')->searchable()->placeholder('—'),
                TextColumn::make('assigned_projects_count')
                    ->label('Proyek aktif')
                    ->counts('assignedProjects')
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->hasVerifiedEmail()),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
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
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada staff')
            ->emptyStateDescription('Tambahkan staff melalui alur undangan aman.');
    }
}
