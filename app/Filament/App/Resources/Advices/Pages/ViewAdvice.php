<?php

namespace App\Filament\App\Resources\Advices\Pages;

use App\Enums\AdviceStatus;
use App\Enums\FollowUpStatus;
use App\Filament\App\Resources\Advices\AdviceResource;
use App\Filament\App\Resources\FollowUps\FollowUpResource;
use App\Models\Advice;
use App\Models\AuditLog;
use App\Models\FollowUp;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAdvice extends ViewRecord
{
    protected static string $resource = AdviceResource::class;

    protected function getHeaderActions(): array
    {
        $advice = $this->getRecord();
        $user = auth()->user();

        return [
            Action::make('approve')->label('Sahkan')->color('success')->icon('heroicon-o-check-badge')->requiresConfirmation()
                ->modalDescription('Pengesahan menjadikan dokumen ini sikap resmi dewan sesuai kewenangan.')
                ->visible(fn () => $advice->status === AdviceStatus::Draf && $user->canIn('nasihat.sahkan', $advice->company_id))
                ->action(function () use ($advice, $user) {
                    $advice->update(['status' => AdviceStatus::Disahkan, 'approved_by' => $user->id, 'approved_at' => now()]);
                    AuditLog::record('approved', $advice);
                }),
            Action::make('deliver')->label('Sampaikan ke direksi')->icon('heroicon-o-paper-airplane')->requiresConfirmation()
                ->visible(fn () => $advice->status === AdviceStatus::Disahkan && ($user->canIn('tindaklanjut.kelola', $advice->company_id)))
                ->action(fn () => $advice->update(['status' => AdviceStatus::Disampaikan, 'delivered_at' => now()])),
            Action::make('response')->label('Catat tanggapan manajemen')->icon('heroicon-o-chat-bubble-left')
                ->visible(fn () => $advice->status === AdviceStatus::Disampaikan && $user->canIn('tindaklanjut.kelola', $advice->company_id))
                ->schema([Textarea::make('management_response')->label('Tanggapan')->required()])
                ->action(fn (array $data) => $advice->update($data + ['status' => AdviceStatus::Ditanggapi])),
            Action::make('followup')->label('Buat tindak lanjut')->icon('heroicon-o-arrow-right-circle')->color('gray')
                ->visible(fn () => $advice->status !== AdviceStatus::Draf && $user->canIn('tindaklanjut.kelola', $advice->company_id))
                ->schema(FollowUpResource::creationFields())
                ->action(function (array $data) use ($advice, $user) {
                    FollowUp::query()->create($data + [
                        'source_type' => Advice::class,
                        'source_id' => $advice->id,
                        'original_target_date' => $data['target_date'],
                        'status' => FollowUpStatus::Draf,
                        'created_by' => $user->id,
                    ]);
                    Notification::make()->title('Tindak lanjut dibuat sebagai draf.')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
