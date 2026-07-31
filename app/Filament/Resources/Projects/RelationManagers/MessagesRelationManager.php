<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Actions\Projects\SendProjectMessage;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Percakapan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project
            && (auth()->user()?->can('viewChat', $ownerRecord) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('sender.name')
                    ->label('Pengirim')
                    ->placeholder('Pengguna nonaktif'),
                TextColumn::make('message')
                    ->label('Pesan')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y H:i', timezone: config('jokiinlah.display_timezone'))
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('sendMessage')
                    ->label('Kirim pesan')
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        Textarea::make('message')
                            ->label('Pesan')
                            ->required()
                            ->maxLength(2000)
                            ->rows(5),
                    ])
                    ->action(fn (array $data) => app(SendProjectMessage::class)->execute(
                        $this->getOwnerRecord(),
                        auth()->user(),
                        $data['message'],
                    ))
                    ->visible(fn (): bool => auth()->user()?->can('sendMessage', $this->getOwnerRecord()) ?? false),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
