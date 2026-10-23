<?php

namespace App\Filament\App\Resources\AiJobs\Pages;

use App\Filament\App\Resources\AiJobs\AiJobResource;
use App\Jobs\RunAiJob;
use App\Services\Ai\AiGateway;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAiJob extends CreateRecord
{
    protected static string $resource = AiJobResource::class;

    protected static ?string $title = 'Permintaan baru ke asisten';

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill(array_filter([
            'mode' => request('mode'),
            'meeting_id' => request('meeting'),
            'information_request_id' => request('request'),
            'document_ids' => request('documents') ? array_map('intval', explode(',', (string) request('documents'))) : null,
        ]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $job = static::getModel()::query()->create([
            'user_id' => auth()->id(),
            'mode' => $data['mode'],
            'prompt' => $data['prompt'],
            'status' => 'antre',
            'scope' => array_filter([
                'document_ids' => $data['document_ids'] ?? null,
                'meeting_id' => $data['meeting_id'] ?? null,
                'information_request_id' => $data['information_request_id'] ?? null,
            ]),
        ]);

        if (config('aksi.ai.run_sync')) {
            app(AiGateway::class)->run($job);
        } else {
            RunAiJob::dispatch($job->id);
        }

        return $job;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
