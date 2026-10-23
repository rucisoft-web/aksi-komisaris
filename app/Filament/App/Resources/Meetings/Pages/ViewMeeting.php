<?php

namespace App\Filament\App\Resources\Meetings\Pages;

use App\Filament\App\Resources\AiJobs\AiJobResource;
use App\Filament\App\Resources\Meetings\MeetingResource;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\MeetingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $meeting = $this->getRecord();

        return [
            Action::make('prepare')
                ->label('Siapkan saya untuk rapat')
                ->icon('heroicon-o-sparkles')
                ->url(fn () => AiJobResource::getUrl('create', ['mode' => 'siapkan_rapat', 'meeting' => $meeting->id]))
                ->visible(fn () => $user->canIn('ai.gunakan', $meeting->company_id)),
            Action::make('conflict')
                ->label('Nyatakan benturan kepentingan')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->schema([
                    Select::make('agenda_item_id')->label('Agenda terkait')->options(fn () => $meeting->agendaItems()->pluck('title', 'id'))->required(),
                    TextInput::make('subject')->label('Pokok benturan')->required(),
                    Textarea::make('nature')->label('Sifat hubungan / kepentingan')->required(),
                    Toggle::make('recusal')->label('Mengundurkan diri dari pembahasan (bahan agenda tertutup untuk saya)')->default(true),
                ])
                ->action(function (array $data) use ($user) {
                    ConflictDeclaration::query()->create($data + ['user_id' => $user->id]);
                    Notification::make()->title('Pernyataan benturan kepentingan dicatat.')->success()->send();
                }),
            Action::make('freeze')
                ->label('Bekukan paket rapat')
                ->icon('heroicon-o-lock-closed')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Setiap bahan dikunci ke versi saat ini. Bahan yang ditambahkan sesudahnya tercatat sebagai adendum.')
                ->visible(fn () => ! $meeting->isFrozen() && $user->canIn('rapat.kelola', $meeting->company_id))
                ->action(function () use ($meeting, $user) {
                    app(MeetingService::class)->freeze($meeting, $user);
                    Notification::make()->title('Paket rapat dibekukan.')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
