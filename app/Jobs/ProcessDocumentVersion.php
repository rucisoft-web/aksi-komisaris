<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\DocumentVersion;
use App\Services\DocumentService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessDocumentVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $versionId) {}

    public function handle(DocumentService $documents, CurrentCompany $current): void
    {
        $version = DocumentVersion::withoutGlobalScopes()->with('company')->find($this->versionId);

        if (! $version) {
            return;
        }

        $current->runAs($version->company, fn () => $documents->process($version));
    }

    public function failed(?Throwable $exception): void
    {
        DocumentVersion::withoutGlobalScopes()->whereKey($this->versionId)->update([
            'processing_status' => ProcessingStatus::Gagal->value,
            'processing_note' => 'Ekstraksi gagal: '.mb_substr((string) $exception?->getMessage(), 0, 500),
        ]);
    }
}
