<x-filament::section>
    <x-slot name="heading">Tindak lanjut mendekati / lewat tenggat</x-slot>
    @forelse ($followUps as $followUp)
        @php $timeliness = $followUp->timelinessLabel(); @endphp
        <a class="aksi-card" href="{{ \App\Filament\App\Resources\FollowUps\FollowUpResource::getUrl('view', ['record' => $followUp]) }}">
            <strong>{{ str($followUp->action_text)->limit(110) }}</strong>
            <span class="aksi-meta {{ $timeliness === 'Lewat tenggat' ? 'aksi-danger' : '' }}">
                {{ $followUp->pic_label ?: $followUp->pic?->name ?: 'PIC belum ditetapkan' }} · tenggat {{ $followUp->target_date->format('d/m/Y') }} · {{ $followUp->status->getLabel() }} · {{ $timeliness }}
            </span>
        </a>
    @empty
        <p class="aksi-muted">Tidak ada tindak lanjut dalam 14 hari ke depan.</p>
    @endforelse
</x-filament::section>
