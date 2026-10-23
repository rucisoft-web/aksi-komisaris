<?php

namespace App\Services\Ai;

use App\Enums\FollowUpStatus;
use App\Enums\RequestStatus;
use App\Enums\SpaceKind;
use App\Models\AgendaMaterial;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Meeting;
use App\Models\Space;
use App\Models\User;
use App\Support\AccessGate;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya jalan keluar ke OpenAI.
 *
 * Alur: cek akses -> kumpulkan sumber -> gerbang kebijakan AI -> Responses API
 * (Structured Outputs + function calling, store: false) -> validasi rujukan dan
 * angka -> simpan sebagai draf. Model hanya mengusulkan pemanggilan fungsi;
 * backend memeriksa izin dan parameter sebelum menjalankannya.
 */
class AiGateway
{
    /** @var array<string, array> */
    protected array $sources = [];

    /** @var array<int, array> */
    protected array $excluded = [];

    /** @var array<string, array> */
    protected array $calculations = [];

    /** @var array<int, array> */
    protected array $toolLog = [];

    public function __construct(
        protected AccessGate $gate,
        protected AiPolicyGate $policy,
        protected Retriever $retriever,
        protected OpenAiClient $client,
        protected OutputValidator $validator,
    ) {}

    public function run(AiJob $job): AiJob
    {
        $started = microtime(true);
        $this->sources = $this->excluded = $this->calculations = $this->toolLog = [];
        $user = $job->user;
        $company = Company::findOrFail($job->company_id);
        $mode = AiModes::all()[$job->mode] ?? throw new RuntimeException('Mode tidak dikenal.');
        $job->update(['status' => 'berjalan', 'model' => config('aksi.ai.model'), 'template_version' => AiModes::VERSION]);

        try {
            if (! $user->canIn('ai.gunakan', $company)) {
                throw new RuntimeException('Peran Anda tidak memiliki izin memakai asisten AI di perusahaan ini.');
            }

            $context = $this->collectContext($job, $user, $company);
            $input = [[
                'role' => 'user',
                'content' => [['type' => 'input_text', 'text' => $this->composeUserMessage($job, $context)]],
            ]];

            $payload = [
                'model' => config('aksi.ai.model'),
                'instructions' => AiModes::BASE_INSTRUCTIONS."\n\nTugas mode ini: ".$mode['instructions'],
                'tools' => AiModes::toolDefinitions($mode['tools']),
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'aksi_output', 'strict' => true, 'schema' => AiModes::outputSchema()]],
                'store' => false,
            ];

            if (str_starts_with((string) config('aksi.ai.model'), 'gpt-5') || str_starts_with((string) config('aksi.ai.model'), 'o')) {
                $payload['include'] = ['reasoning.encrypted_content'];
            }

            $usage = ['input' => 0, 'output' => 0];
            $text = null;

            for ($round = 0; $round <= config('aksi.ai.max_tool_rounds'); $round++) {
                $response = $this->client->respond($payload + ['input' => $input]);
                $usage['input'] += (int) data_get($response, 'usage.input_tokens', 0);
                $usage['output'] += (int) data_get($response, 'usage.output_tokens', 0);
                $outputItems = $response['output'] ?? [];
                $calls = array_values(array_filter($outputItems, fn ($item) => ($item['type'] ?? null) === 'function_call'));

                if ($calls === [] || $round === config('aksi.ai.max_tool_rounds')) {
                    $text = $this->extractText($outputItems);

                    break;
                }

                // store:false -> riwayat dikirim ulang lengkap, termasuk item reasoning.
                array_push($input, ...$outputItems);

                foreach ($calls as $call) {
                    $input[] = [
                        'type' => 'function_call_output',
                        'call_id' => $call['call_id'],
                        'output' => json_encode($this->executeTool($call, $mode['tools'], $job, $user, $company), JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            $decoded = json_decode((string) $text, true);

            if (! is_array($decoded) || ! isset($decoded['items'])) {
                throw new RuntimeException('Keluaran AI tidak sesuai skema.');
            }

            $validated = $this->validator->validate($decoded, $this->sources, $this->calculations);

            $job->update([
                'status' => 'selesai',
                'request_sha256' => hash('sha256', json_encode([$payload['instructions'], $job->prompt, array_keys($this->sources)])),
                'sources' => $this->publicSources(),
                'excluded_sources' => $this->excluded,
                'calculations' => $this->calculations,
                'tool_calls' => $this->toolLog,
                'output' => $validated['output'],
                'validation' => $validated['report'],
                'input_tokens' => $usage['input'],
                'output_tokens' => $usage['output'],
                'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => 'gagal',
                'error' => $e->getMessage(),
                'sources' => $this->publicSources(),
                'excluded_sources' => $this->excluded,
                'tool_calls' => $this->toolLog,
                'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        }

        return $job->refresh();
    }

    /** @return array{notes: array<int, string>} */
    protected function collectContext(AiJob $job, User $user, Company $company): array
    {
        $scope = $job->scope ?? [];
        $notes = [];
        $limit = (int) config('aksi.ai.max_sources');

        if (! empty($scope['meeting_id'])) {
            $meeting = Meeting::withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($scope['meeting_id']);

            if (! $this->gate->canViewSpace($user, Space::withoutGlobalScopes()->findOrFail($meeting->space_id))) {
                throw new RuntimeException('Anda tidak memiliki akses ke rapat ini.');
            }

            $notes[] = 'Rapat: '.$meeting->title.' pada '.$meeting->scheduled_at->translatedFormat('l, d F Y H:i').'.';
            $notes[] = 'Paket rapat: '.($meeting->isFrozen() ? 'dibekukan pada '.$meeting->frozen_at->format('d/m/Y H:i') : 'belum dibekukan').'.';

            foreach ($meeting->agendaItems()->withoutGlobalScopes()->get() as $item) {
                $materials = AgendaMaterial::withoutGlobalScopes()->where('agenda_item_id', $item->id)->get();
                $notes[] = "Agenda {$item->sort}: {$item->title}".($materials->isEmpty() ? ' (BELUM ADA BAHAN)' : ' ('.$materials->count().' bahan)');

                foreach ($materials as $material) {
                    $versionId = $material->document_version_id ?? Document::withoutGlobalScopes()->find($material->document_id)?->current_version_id;
                    $this->addChunks($this->retriever->baseQuery($user, $company)
                        ->withoutGlobalScopes()
                        ->where('document_chunks.document_version_id', $versionId)
                        ->orderBy('seq')->limit(4)->get(), $company);
                }
            }
        }

        if (! empty($scope['information_request_id'])) {
            $request = InformationRequest::withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($scope['information_request_id']);

            if (! $this->gate->canViewSpace($user, Space::withoutGlobalScopes()->findOrFail($request->space_id))) {
                throw new RuntimeException('Anda tidak memiliki akses ke permintaan informasi ini.');
            }

            $this->addSource(['type' => 'pertanyaan_dewan', 'title' => 'Pertanyaan dewan', 'location' => 'permintaan #'.$request->id, 'text' => $request->question."\n\nTujuan: ".$request->purpose, 'classification' => 'internal']);

            foreach ($request->responses()->withoutGlobalScopes()->get() as $response) {
                $this->addSource(['type' => 'jawaban_manajemen', 'title' => 'Jawaban manajemen', 'location' => 'jawaban '.$response->created_at->format('d/m/Y'), 'text' => $response->body, 'classification' => 'internal']);

                if ($response->document_id) {
                    $this->addDocumentChunks($user, $company, [$response->document_id], $request->question, 6);
                }
            }
        }

        if (! empty($scope['document_ids'])) {
            $this->addDocumentChunks($user, $company, array_map('intval', $scope['document_ids']), $job->prompt, $limit);
        }

        if (empty($scope['document_ids']) && empty($scope['meeting_id']) && empty($scope['information_request_id'])) {
            $this->addChunks($this->retriever->search($user, $company, $job->prompt, null, min(12, $limit)), $company);
        }

        return ['notes' => $notes];
    }

    protected function addDocumentChunks(User $user, Company $company, array $documentIds, string $query, int $limit): void
    {
        $perDocument = max(3, intdiv($limit, max(1, count($documentIds))));

        foreach ($documentIds as $documentId) {
            $document = Document::withoutGlobalScopes()->where('company_id', $company->id)->find($documentId);

            if (! $document || ! $this->gate->canViewDocument($user, $document)) {
                $this->excluded[] = ['title' => 'Dokumen #'.$documentId, 'reason' => 'Tidak ada akses.'];

                continue;
            }

            $chunks = $this->retriever->search($user, $company, $query, [$documentId], $perDocument);

            if ($chunks->count() < 2) {
                $chunks = $chunks->merge($this->retriever->baseQuery($user, $company)
                    ->where('document_chunks.document_id', $documentId)->orderBy('seq')->limit($perDocument)->get())->unique('id');
            }

            if ($chunks->isEmpty()) {
                $version = $document->currentVersion()->withoutGlobalScopes()->first();
                $this->excluded[] = ['title' => $document->title, 'reason' => $version ? 'Belum ada teks yang dapat dibaca ('.$version->processing_status->getLabel().').' : 'Belum ada berkas.'];
            }

            $this->addChunks($chunks, $company);
        }
    }

    /** @param Collection<int, DocumentChunk> $chunks */
    protected function addChunks(Collection $chunks, Company $company): array
    {
        $added = [];

        foreach ($chunks as $chunk) {
            if (count($this->sources) >= (int) config('aksi.ai.max_sources') * 2) {
                break;
            }

            if (collect($this->sources)->contains('chunk_id', $chunk->id)) {
                $added[] = collect($this->sources)->search(fn ($s) => ($s['chunk_id'] ?? null) === $chunk->id);

                continue;
            }

            $reason = $this->policy->denialReason($company, $chunk->document, $chunk->version);

            if ($reason !== null) {
                $key = $chunk->document_id.':'.$reason;

                if (! collect($this->excluded)->contains('key', $key)) {
                    $this->excluded[] = ['key' => $key, 'title' => $chunk->document->title, 'reason' => $reason];
                }

                continue;
            }

            $added[] = $this->addSource([
                'type' => 'dokumen',
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->document_id,
                'version_id' => $chunk->document_version_id,
                'version_no' => $chunk->version->version_no,
                'page_no' => $chunk->page_no,
                'title' => $chunk->document->title,
                'location' => $chunk->locationLabel(),
                'period' => $chunk->document->periodLabel(),
                'status' => $chunk->document->status->getLabel(),
                'classification' => $chunk->classification->value,
                'text' => $chunk->content,
            ]);
        }

        return $added;
    }

    protected function addSource(array $source): string
    {
        $id = 'S'.(count($this->sources) + 1);
        $this->sources[$id] = $source + ['id' => $id];

        return $id;
    }

    protected function composeUserMessage(AiJob $job, array $context): string
    {
        $blocks = collect($this->sources)->map(fn ($s) => sprintf(
            "<sumber id=\"%s\" judul=\"%s\" lokasi=\"%s\"%s>\n%s\n</sumber>",
            $s['id'],
            e($s['title']),
            e($s['location'] ?? ''),
            isset($s['period']) ? ' periode="'.e($s['period']).'" status="'.e($s['status']).'"' : ' jenis="'.e($s['type']).'"',
            $s['text'],
        ))->implode("\n\n");

        $excluded = collect($this->excluded)->map(fn ($e) => '- '.$e['title'].': '.$e['reason'])->implode("\n");

        return trim(implode("\n\n", array_filter([
            "Permintaan pengguna:\n".$job->prompt,
            $context['notes'] ? "Konteks:\n".implode("\n", $context['notes']) : null,
            $excluded ? "Sumber yang TIDAK disertakan (sebutkan sebagai keterbatasan bila relevan):\n".$excluded : null,
            $blocks ?: 'Tidak ada sumber yang boleh dikirim. Nyatakan bahwa informasi belum tersedia.',
        ])));
    }

    protected function executeTool(array $call, array $allowed, AiJob $job, User $user, Company $company): array
    {
        $name = $call['name'] ?? '';
        $args = json_decode($call['arguments'] ?? '{}', true) ?: [];
        $entry = ['name' => $name, 'arguments' => $args, 'allowed' => in_array($name, $allowed, true)];

        if (! $entry['allowed']) {
            $this->toolLog[] = $entry + ['result' => 'ditolak'];

            return ['error' => 'Fungsi tidak diizinkan untuk mode ini.'];
        }

        $result = match ($name) {
            'search_authorized_documents' => $this->toolSearch($user, $company, (string) ($args['query'] ?? '')),
            'get_open_followups' => $this->toolFollowUps($user, $company),
            'calculate_variance' => $this->toolVariance($args),
            'create_draft_question' => $this->toolDraftQuestion($user, $company, $job, $args),
            default => ['error' => 'Fungsi tidak dikenal.'],
        };

        $this->toolLog[] = $entry + ['result' => array_key_exists('error', $result) ? $result['error'] : 'ok'];

        return $result;
    }

    protected function toolSearch(User $user, Company $company, string $query): array
    {
        if (mb_strlen(trim($query)) < 3) {
            return ['error' => 'Kueri terlalu pendek.'];
        }

        $ids = $this->addChunks($this->retriever->search($user, $company, $query, null, 6), $company);

        return ['sources' => collect($ids)->filter()->map(fn ($id) => [
            'id' => $id,
            'judul' => $this->sources[$id]['title'],
            'lokasi' => $this->sources[$id]['location'],
            'teks' => $this->sources[$id]['text'],
        ])->values()->all()];
    }

    protected function toolFollowUps(User $user, Company $company): array
    {
        if (! $user->canIn('tindaklanjut.lihat', $company)) {
            return ['error' => 'Tidak ada izin melihat tindak lanjut.'];
        }

        $items = FollowUp::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNot('status', FollowUpStatus::Ditutup->value)
            ->orderBy('target_date')
            ->limit(20)
            ->get();

        $text = $items->map(fn (FollowUp $f) => sprintf('#%d %s | PIC: %s | tenggat: %s | status: %s | ketepatan: %s',
            $f->id, $f->action_text, $f->pic_label ?? $f->pic?->name ?? '-', $f->target_date->format('d/m/Y'), $f->status->getLabel(), $f->timelinessLabel()))->implode("\n");

        $id = $this->addSource(['type' => 'tindak_lanjut', 'title' => 'Daftar tindak lanjut terbuka', 'location' => 'per '.now()->format('d/m/Y'), 'text' => $text ?: 'Tidak ada tindak lanjut terbuka.', 'classification' => 'internal']);

        return ['source_id' => $id, 'teks' => $this->sources[$id]['text']];
    }

    protected function toolVariance(array $args): array
    {
        $actual = $args['actual'] ?? null;
        $baseline = $args['baseline'] ?? null;

        if (! is_numeric($actual) || ! is_numeric($baseline)) {
            return ['error' => 'actual dan baseline harus angka.'];
        }

        $sourceIds = array_values(array_filter($args['source_ids'] ?? [], fn ($id) => isset($this->sources[$id])));

        if ($sourceIds === []) {
            return ['error' => 'Sertakan source_ids yang valid sebagai asal angka.'];
        }

        $difference = (float) $actual - (float) $baseline;
        $percent = (float) $baseline == 0.0 ? null : round($difference / abs((float) $baseline) * 100, 2);
        $id = 'C'.(count($this->calculations) + 1);

        $this->calculations[$id] = [
            'id' => $id,
            'label' => (string) ($args['label'] ?? ''),
            'actual' => (float) $actual,
            'baseline' => (float) $baseline,
            'difference' => round($difference, 4),
            'percent' => $percent,
            'unit' => (string) ($args['unit'] ?? ''),
            'source_ids' => $sourceIds,
        ];

        return $this->calculations[$id];
    }

    protected function toolDraftQuestion(User $user, Company $company, AiJob $job, array $args): array
    {
        if (! $user->canIn('permintaan.buat', $company)) {
            return ['error' => 'Tidak ada izin membuat pertanyaan.'];
        }

        $private = Space::withoutGlobalScopes()->where('company_id', $company->id)
            ->where('kind', SpaceKind::Pribadi->value)->where('owner_user_id', $user->id)->first();

        if (! $private) {
            return ['error' => 'Ruang pribadi tidak ditemukan.'];
        }

        $request = InformationRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'space_id' => $private->id,
            'question' => (string) ($args['question'] ?? ''),
            'purpose' => (string) ($args['purpose'] ?? ''),
            'basis' => 'Draf dari asisten AI (permintaan #'.$job->id.').',
            'status' => RequestStatus::Draf,
            'created_by' => $user->id,
        ]);

        return ['draft_id' => $request->id, 'status' => 'Disimpan sebagai draf di ruang pribadi. Belum dikirim.'];
    }

    protected function extractText(array $outputItems): ?string
    {
        foreach ($outputItems as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return $content['text'];
                }

                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('Model menolak permintaan: '.$content['refusal']);
                }
            }
        }

        return null;
    }

    /** Sumber yang disimpan di ai_jobs (teks dipotong agar log tidak menggandakan isi dokumen). */
    protected function publicSources(): array
    {
        return collect($this->sources)->map(fn ($s) => array_merge($s, ['text' => mb_substr($s['text'], 0, 1500)]))->all();
    }
}
