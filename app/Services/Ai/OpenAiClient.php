<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien tipis untuk OpenAI Responses API, dengan circuit breaker
 * agar gangguan API tidak membanjiri antrean.
 */
class OpenAiClient
{
    public function isAvailable(): bool
    {
        return filled(config('aksi.ai.api_key')) && ! Cache::has('aksi:ai:circuit-open');
    }

    public function respond(array $payload): array
    {
        if (blank(config('aksi.ai.api_key'))) {
            throw new RuntimeException('OPENAI_API_KEY belum diisi di .env.');
        }

        if (Cache::has('aksi:ai:circuit-open')) {
            throw new RuntimeException('Asisten sementara tidak tersedia karena gangguan layanan AI. Coba lagi beberapa menit lagi.');
        }

        $response = Http::withToken(config('aksi.ai.api_key'))
            ->acceptJson()
            ->timeout(config('aksi.ai.timeout'))
            ->post(rtrim(config('aksi.ai.base_url'), '/').'/responses', $payload);

        if ($response->failed()) {
            $this->recordFailure();

            throw new RuntimeException('Layanan AI mengembalikan galat '.$response->status().': '.mb_substr((string) $response->json('error.message', $response->body()), 0, 300));
        }

        Cache::forget('aksi:ai:failures');

        return $response->json();
    }

    protected function recordFailure(): void
    {
        $failures = Cache::increment('aksi:ai:failures');

        if ($failures >= config('aksi.ai.circuit_breaker_failures')) {
            Cache::put('aksi:ai:circuit-open', true, now()->addMinutes(config('aksi.ai.circuit_breaker_minutes')));
            Cache::forget('aksi:ai:failures');
        }
    }
}
