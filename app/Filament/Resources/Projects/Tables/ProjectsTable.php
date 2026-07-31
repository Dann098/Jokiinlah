<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_code')->label('Kode')->searchable()->copyable(),
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->limit(55),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextColumn::make('assignedStaff.name')
                    ->label('Staff')
                    ->searchable()
                    ->placeholder('Belum ditugaskan')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('unread_chat')
                    ->label('Chat belum dibaca')
                    ->state(fn ($record): int => $record->unreadMessagesFor(auth()->user()))
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? $state.' baru' : 'Sudah dibaca')
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('progress')->label('Progress')->suffix('%')->sortable(),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->dateTime('d M Y', timezone: config('jokiinlah.display_timezone'))
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ProjectStatus::class),
                SelectFilter::make('assigned_staff_id')
                    ->label('Staff')
                    ->relationship('assignedStaff', 'name')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                SelectFilter::make('payment_status')
                    ->label('Pembayaran')
                    ->options(PaymentStatus::class)
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
                EditAction::make()
                    ->label('Edit detail')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Belum ada proyek')
            ->emptyStateDescription(fn (): string => auth()->user()?->isStaff()
                ? 'Belum ada proyek yang ditugaskan kepada Anda.'
                : 'Buat proyek manual atau konversi konsultasi yang telah ditinjau.');
    }
}
