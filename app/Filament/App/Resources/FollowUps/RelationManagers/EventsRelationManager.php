<?php

namespace App\Filament\App\Resources\FollowUps\RelationManagers;

use App\Enums\FollowUpStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $modelLabel = 'riwayat';

    protected static ?string $pluralModelLabel = 'riwayat';

    protected static ?string $title = 'Riwayat';

    public function table(Table $table): Table
    {
        $label = fn ($state) => $state ? FollowUpStatus::tryFrom($state)?->getLabel() ?? $state : '—';

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('user.name')->label('Oleh')->placeholder('Sistem'),
                TextColumn::make('from_status')->label('Dari')->formatStateUsing($label),
                TextColumn::make('to_status')->label('Ke')->formatStateUsing($label),
                TextColumn::make('note')->label('Catatan')->wrap(),
            ]);
    }
}
