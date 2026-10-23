@php
    use App\Filament\App\Resources\AiJobs\AiJobResource;
    use App\Filament\App\Resources\Documents\DocumentResource;
    use App\Filament\App\Resources\FollowUps\FollowUpResource;
    use App\Filament\App\Resources\InformationRequests\InformationRequestResource;
    use App\Filament\App\Resources\Issues\IssueResource;
    use App\Filament\App\Resources\Meetings\MeetingResource;
    use App\Filament\App\Resources\PersonalNotes\PersonalNoteResource;
@endphp

<x-filament-panels::page>
    <p class="aksi-muted">{{ $role?->getLabel() }} · {{ filament()->getTenant()->name }} · {{ now()->translatedFormat('l, d F Y') }}</p>

    @if ($isLiaison)
        <div class="aksi-grid aksi-grid-2">
            <x-filament::section>
                <x-slot name="heading">Permintaan informasi untuk Anda</x-slot>
                @forelse ($requests as $request)
                    <a class="aksi-card" href="{{ InformationRequestResource::getUrl('view', ['record' => $request]) }}">
                        <strong>{{ str($request->question)->limit(140) }}</strong>
                        <span class="aksi-meta">Tenggat {{ $request->due_date?->format('d/m/Y') ?? '—' }}</span>
                    </a>
                @empty
                    <p class="aksi-muted">Tidak ada permintaan yang menunggu jawaban.</p>
                @endforelse
            </x-filament::section>
            @include('filament.pages.partials.followups', ['followUps' => $followUps])
        </div>
    @else
        <div class="aksi-grid aksi-grid-3">
            <x-filament::section class="aksi-span-2">
                <x-slot name="heading">Perhatian utama</x-slot>
                <x-slot name="description">Diurutkan menurut materialitas. Tidak ada skor tunggal kesehatan perusahaan.</x-slot>
                @forelse ($issues as $issue)
                    <a class="aksi-card" href="{{ IssueResource::getUrl('edit', ['record' => $issue]) }}">
                        <div class="aksi-row">
                            <x-filament::badge :color="$issue->materiality->getColor()">{{ $issue->materiality->getLabel() }}</x-filament::badge>
                            <x-filament::badge :color="$issue->evidence_status->getColor()">{{ $issue->evidence_status->getLabel() }}</x-filament::badge>
                            <strong>{{ $issue->title }}</strong>
                        </div>
                        <span class="aksi-meta">
                            Periode data: {{ $issue->data_period_end?->format('d/m/Y') ?? 'tidak diisi' }} ·
                            Diperbarui {{ $issue->updated_at->diffForHumans() }} ·
                            Sumber: {{ $issue->source_note ?: 'belum dicatat' }} ·
                            Pemilik: {{ $issue->owner?->name ?? '—' }} · {{ $issue->status->getLabel() }}
                        </span>
                    </a>
                @empty
                    <p class="aksi-muted">Belum ada isu terbuka.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Persiapan rapat</x-slot>
                @forelse ($nextMeetings as $row)
                    <div class="aksi-card">
                        <a href="{{ MeetingResource::getUrl('view', ['record' => $row['meeting']]) }}"><strong>{{ $row['meeting']->title }}</strong></a>
                        <span class="aksi-meta">{{ $row['meeting']->scheduled_at->translatedFormat('D, d M Y H:i') }} · {{ $row['agenda_count'] }} agenda · {{ $row['materials_count'] }} bahan · {{ $row['meeting']->isFrozen() ? 'paket beku' : 'paket terbuka' }}</span>
                        @if ($row['missing']->isNotEmpty())
                            <span class="aksi-danger">Belum ada bahan: {{ $row['missing']->implode(', ') }}</span>
                        @endif
                        @if (auth()->user()->canIn('ai.gunakan', filament()->getTenant()))
                            <a class="aksi-link" href="{{ AiJobResource::getUrl('create', ['mode' => 'siapkan_rapat', 'meeting' => $row['meeting']->id]) }}">Siapkan saya untuk rapat →</a>
                        @endif
                    </div>
                @empty
                    <p class="aksi-muted">Tidak ada rapat terjadwal.</p>
                @endforelse
            </x-filament::section>

            @include('filament.pages.partials.followups', ['followUps' => $followUps])

            <x-filament::section>
                <x-slot name="heading">Informasi belum tersedia</x-slot>
                @foreach ($missingDocs as $receipt)
                    <div class="aksi-card">
                        <strong>{{ $receipt->requiredDocument->title }} · {{ $receipt->period_label }}</strong>
                        <span class="aksi-danger">Tenggat {{ $receipt->due_date->format('d/m/Y') }} · {{ str_replace('_', ' ', $receipt->status) }}</span>
                    </div>
                @endforeach
                @foreach ($pendingRequests as $request)
                    <a class="aksi-card" href="{{ InformationRequestResource::getUrl('view', ['record' => $request]) }}">
                        <strong>{{ str($request->question)->limit(100) }}</strong>
                        <span class="aksi-meta {{ $request->isOverdue() ? 'aksi-danger' : '' }}">Menunggu jawaban · tenggat {{ $request->due_date?->format('d/m/Y') ?? '—' }}</span>
                    </a>
                @endforeach
                @if ($missingDocs->isEmpty() && $pendingRequests->isEmpty())
                    <p class="aksi-muted">Tidak ada dokumen atau jawaban yang masih ditunggu.</p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Perubahan penting (14 hari)</x-slot>
                @forelse ($changes as $document)
                    <a class="aksi-card" href="{{ DocumentResource::getUrl('view', ['record' => $document]) }}">
                        <strong>{{ $document->title }}</strong>
                        <span class="aksi-meta">v{{ $document->currentVersion?->version_no }} · {{ $document->updated_at->diffForHumans() }} · {{ $document->periodLabel() }}</span>
                    </a>
                @empty
                    <p class="aksi-muted">Tidak ada dokumen baru.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Brief AI terakhir</x-slot>
                @if ($brief)
                    <p>{{ str($brief->output['summary'] ?? '')->limit(320) }}</p>
                    <span class="aksi-meta">{{ count($brief->sources ?? []) }} sumber · {{ $brief->created_at->diffForHumans() }} · DRAF</span>
                    <a class="aksi-link" href="{{ AiJobResource::getUrl('view', ['record' => $brief]) }}">Buka dengan sumber →</a>
                @else
                    <p class="aksi-muted">Belum ada brief. <a class="aksi-link" href="{{ AiJobResource::getUrl('create') }}">Minta asisten</a></p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Catatan pribadi</x-slot>
                @forelse ($notes as $note)
                    <a class="aksi-card" href="{{ PersonalNoteResource::getUrl('edit', ['record' => $note]) }}">
                        <strong>{{ $note->title }}</strong>
                        <span class="aksi-meta">{{ $note->updated_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="aksi-muted">Belum ada catatan.</p>
                @endforelse
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
