<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Services\Ai\AiGateway;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $aiJobId) {}

    public function handle(AiGateway $gateway, CurrentCompany $current): void
    {
        $job = AiJob::withoutGlobalScopes()->with(['company', 'user'])->find($this->aiJobId);

        if ($job && $job->status === 'antre') {
            $current->runAs($job->company, fn () => $gateway->run($job));
        }
    }
}
