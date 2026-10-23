<?php

namespace App\Filament\App\Resources\AiJobs\Pages;

use App\Enums\RequestStatus;
use App\Enums\SpaceKind;
use App\Filament\App\Resources\AiJobs\AiJobResource;
use App\Jobs\RunAiJob;
use App\Models\InformationRequest;
use App\Models\PersonalNote;
use App\Models\Space;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiModes;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAiJob extends ViewRecord
{
    protected static string $resource = AiJobResource::class;

    protected string $view = 'filament.ai.view-ai-job';

    public function getTitle(): string
    {
        return AiModes::options()[$this->getRecord()->mode] ?? 'Hasil asisten';
    }

    public function isPending(): bool
    {
        return in_array($this->getRecord()->status, ['antre', 'berjalan'], true);
    }

    public function refreshJob(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        $job = $this->getRecord();

        return [
            Action::make('save_note')
                ->label('Simpan sebagai catatan pribadi')
                ->icon('heroicon-o-bookmark')
                ->visible(fn () => $job->status === 'selesai')
                ->action(function () use ($job) {
                    PersonalNote::query()->create([
                        'user_id' => auth()->id(),
                        'title' => 'Hasil asisten: '.str($job->prompt)->limit(60),
                        'body' => $this->renderPlain(),
                    ]);
                    Notification::make()->title('Disimpan ke catatan pribadi.')->success()->send();
                }),
            Action::make('save_questions')
                ->label('Jadikan draf pertanyaan')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->visible(fn () => $job->status === 'selesai' && collect($job->output['items'] ?? [])->contains('kind', 'question'))
                ->action(function () use ($job) {
                    $private = Space::query()->where('kind', SpaceKind::Pribadi->value)->where('owner_user_id', auth()->id())->first();
                    $count = 0;

                    foreach (collect($job->output['items'])->where('kind', 'question') as $item) {
                        InformationRequest::query()->create([
                            'space_id' => $private->id,
                            'question' => $item['text'],
                            'basis' => 'Dari asisten AI #'.$job->id.'; sumber: '.collect($item['citations'])->pluck('source_id')->implode(', '),
                            'status' => RequestStatus::Draf,
                            'created_by' => auth()->id(),
                        ]);
                        $count++;
                    }

                    Notification::make()->title($count.' draf pertanyaan disimpan di ruang pribadi.')->success()->send();
                }),
            Action::make('retry')
                ->label('Jalankan ulang')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $job->status === 'gagal')
                ->action(function () use ($job) {
                    $job->update(['status' => 'antre', 'error' => null]);
                    config('aksi.ai.run_sync') ? app(AiGateway::class)->run($job) : RunAiJob::dispatch($job->id);
                }),
        ];
    }

    public function renderPlain(): string
    {
        $job = $this->getRecord();
        $lines = ['Ringkasan: '.($job->output['summary'] ?? '')];

        foreach ($job->output['items'] ?? [] as $item) {
            $lines[] = '- ['.($item['kind']).'] '.$item['text'].' ('.collect($item['citations'])->pluck('source_id')->implode(', ').')';
        }

        return implode("\n", $lines)."\n\n(Draf AI, belum ditelaah.)";
    }
}
