<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail proyek')
                    ->description('Kode, status, progress, penugasan, dan pembayaran dikelola oleh action server terpisah.')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship(
                                'customer',
                                'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('role', UserRole::Customer->value)
                                    ->where('is_active', true),
                            )
                            ->searchable()
                            ->preload(false)
                            ->required()
                            ->disabledOn('edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        Select::make('assigned_staff_id')
                            ->label('Staff awal')
                            ->relationship(
                                'assignedStaff',
                                'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('role', UserRole::Staff->value)
                                    ->where('is_active', true),
                            )
                            ->searchable()
                            ->preload(false)
                            ->visibleOn('create'),
                        Select::make('service_id')
                            ->label('Layanan')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload(false)
                            ->required(),
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->maxLength(10000)
                            ->columnSpanFull(),
                        DateTimePicker::make('start_date')
                            ->label('Tanggal mulai')
                            ->timezone(config('jokiinlah.display_timezone')),
                        DateTimePicker::make('deadline')
                            ->label('Deadline')
                            ->timezone(config('jokiinlah.display_timezone')),
                        Textarea::make('admin_note')
                            ->label('Catatan internal admin')
                            ->maxLength(3000)
                            ->visibleOn('edit')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
