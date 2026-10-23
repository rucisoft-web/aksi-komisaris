<?php

namespace App\Filament\App\Resources\FollowUps\Pages;

use App\Enums\FollowUpStatus;
use App\Filament\App\Resources\FollowUps\FollowUpResource;
use App\Services\FollowUpService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFollowUp extends ViewRecord
{
    protected static string $resource = FollowUpResource::class;

    protected function getHeaderActions(): array
    {
        $followUp = $this->getRecord();
        $service = app(FollowUpService::class);
        $actions = [];

        foreach ($service->availableTransitions($followUp, auth()->user()) as $to => $label) {
            $needsNote = in_array($to, ['dilaksanakan', 'dibuka_kembali'], true) && $followUp->status === FollowUpStatus::Diperiksa
                || $to === 'dibuka_kembali';

            $actions[] = Action::make('to_'.$to)
                ->label(match ($to) {
                    'disahkan' => 'Sahkan',
                    'disampaikan' => 'Sampaikan ke PIC',
                    'ditanggapi' => 'Tanggapi',
                    'dilaksanakan' => $followUp->status === FollowUpStatus::Diperiksa ? 'Kembalikan: bukti kurang' : 'Mulai laksanakan',
                    'diperiksa' => 'Ajukan pemeriksaan',
                    'ditutup' => 'Tutup',
                    'dibuka_kembali' => 'Buka kembali',
                    default => $label,
                })
                ->color(match ($to) {
                    'ditutup' => 'success', 'dibuka_kembali', 'dilaksanakan' => $followUp->status === FollowUpStatus::Diperiksa || $to === 'dibuka_kembali' ? 'danger' : 'primary', default => 'primary'
                })
                ->requiresConfirmation()
                ->schema([Textarea::make('note')->label($needsNote ? 'Alasan (wajib)' : 'Catatan')->required($needsNote)])
                ->action(function (array $data) use ($service, $followUp, $to) {
                    $service->transition($followUp, FollowUpStatus::from($to), auth()->user(), $data['note'] ?? null);
                    Notification::make()->title('Status diperbarui.')->success()->send();
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $followUp]));
                });
        }

        $actions[] = EditAction::make();

        return $actions;
    }
}
