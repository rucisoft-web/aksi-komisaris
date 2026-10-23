<?php

namespace App\Filament\App\Resources\FollowUps\RelationManagers;

use App\Models\FollowUpDeadlineChange;
use App\Services\FollowUpService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeadlineChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlineChanges';

    protected static ?string $modelLabel = 'perubahan tenggat';

    protected static ?string $pluralModelLabel = 'perubahan tenggat';

    protected static ?string $title = 'Perubahan tenggat';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $followUp = $this->getOwnerRecord();
        $user = auth()->user();
        $service = app(FollowUpService::class);

        return $table
            ->columns([
                TextColumn::make('old_date')->label('Dari')->date('d/m/Y'),
                TextColumn::make('new_date')->label('Menjadi')->date('d/m/Y'),
                TextColumn::make('reason')->label('Alasan')->wrap(),
                TextColumn::make('requester.name')->label('Diajukan oleh'),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => match ($state) {
                    'disetujui' => 'success', 'ditolak' => 'danger', default => 'warning'
                }),
            ])
            ->headerActions([
                Action::make('request')
                    ->label('Ajukan perubahan tenggat')
                    ->visible(fn () => $followUp->isOpen() && ($followUp->pic_user_id === $user->id || $user->canIn('tindaklanjut.kelola', $followUp->company_id)))
                    ->schema([
                        DatePicker::make('new_date')->label('Tenggat baru')->required()->after('today'),
                        Textarea::make('reason')->label('Alasan')->required(),
                    ])
                    ->action(function (array $data) use ($service, $followUp, $user) {
                        $service->requestDeadlineChange($followUp, $data['new_date'], $data['reason'], $user);
                        Notification::make()->title('Pengajuan dicatat dan menunggu persetujuan.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('approve')->label('Setujui')->color('success')->requiresConfirmation()
                    ->visible(fn (FollowUpDeadlineChange $record) => $record->status === 'diajukan' && $user->canIn('tenggat.setujui', $followUp->company_id))
                    ->action(fn (FollowUpDeadlineChange $record) => $service->decideDeadlineChange($record, true, $user)),
                Action::make('reject')->label('Tolak')->color('danger')->requiresConfirmation()
                    ->visible(fn (FollowUpDeadlineChange $record) => $record->status === 'diajukan' && $user->canIn('tenggat.setujui', $followUp->company_id))
                    ->action(fn (FollowUpDeadlineChange $record) => $service->decideDeadlineChange($record, false, $user)),
            ]);
    }
}
