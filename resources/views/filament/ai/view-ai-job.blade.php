@php
    $job = $this->getRecord();
    $output = $job->output ?? [];
    $sources = $job->sources ?? [];
    $kinds = [
        'fact' => ['Fakta dari dokumen', 'success'],
        'calculation' => ['Hasil perhitungan', 'info'],
        'management_explanation' => ['Penjelasan manajemen', 'primary'],
        'analysis' => ['Analisis / dugaan', 'warning'],
        'missing_info' => ['Informasi belum tersedia', 'danger'],
        'question' => ['Pertanyaan', 'gray'],
        'proposed_action' => ['Usulan tindakan', 'gray'],
    ];
    $grouped = collect($output['items'] ?? [])->groupBy('kind');
@endphp

<x-filament-panels::page>
    <div @if ($this->isPending()) wire:poll.3s="refreshJob" @endif>
        <div style="padding:10px 14px;border-radius:10px;background:rgba(245,158,11,.12);font-size:13px;margin-bottom:16px;">
            <strong>DRAF.</strong> Hasil asisten belum ditelaah dan bukan sikap resmi dewan. Periksa setiap klaim pada sumbernya.
        </div>

        <x-filament::section>
            <x-slot name="heading">Permintaan</x-slot>
            <p style="white-space:pre-line">{{ $job->prompt }}</p>
        </x-filament::section>

        @if ($this->isPending())
            <x-filament::section class="mt-4">
                <div class="flex items-center gap-3">
                    <x-filament::loading-indicator class="h-5 w-5" />
                    <span>Asisten sedang menelaah sumber… ({{ $job->status }}). Halaman ini diperbarui otomatis.</span>
                </div>
                @unless (config('aksi.ai.run_sync'))
                    <p class="text-sm text-gray-500 mt-2">Permintaan diproses oleh antrean yang dijalankan cron setiap menit.</p>
                @endunless
            </x-filament::section>
        @elseif ($job->status === 'gagal')
            <x-filament::section class="mt-4">
                <x-slot name="heading">Asisten tidak dapat menyelesaikan permintaan</x-slot>
                <p class="text-danger-600">{{ $job->error }}</p>
                <p class="text-sm text-gray-500 mt-2">Dokumen, rapat, pertanyaan, dan tindak lanjut tetap dapat dipakai seperti biasa.</p>
            </x-filament::section>
        @else
            <div x-data="{ src: null }" class="grid gap-4 lg:grid-cols-5 mt-4">
                <div class="lg:col-span-3 space-y-4">
                    <x-filament::section>
                        <x-slot name="heading">Ringkasan</x-slot>
                        <p style="white-space:pre-line">{{ $output['summary'] ?? '' }}</p>
                    </x-filament::section>

                    @foreach ($kinds as $kind => [$label, $color])
                        @continue(! $grouped->has($kind))
                        <x-filament::section>
                            <x-slot name="heading">
                                <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
                            </x-slot>
                            <ul class="space-y-3">
                                @foreach ($grouped[$kind] as $item)
                                    <li class="border-b border-gray-100 dark:border-white/10 pb-3">
                                        <div class="flex gap-2 items-start">
                                            @if (($item['materiality'] ?? null) === 'tinggi')
                                                <x-filament::badge color="danger" size="sm" class="shrink-0">material</x-filament::badge>
                                            @endif
                                            <span>{{ $item['text'] }}</span>
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach ($item['citations'] ?? [] as $citation)
                                                <button type="button" x-on:click="src = @js($citation['source_id'])"
                                                    class="text-xs px-2 py-0.5 rounded-full {{ ($citation['verified'] ?? false) ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' : 'bg-danger-50 text-danger-700' }}"
                                                    title="{{ $citation['quote'] }}">
                                                    {{ $citation['source_id'] }}{{ ($citation['verified'] ?? false) ? '' : ' (kutipan tidak cocok)' }}
                                                </button>
                                            @endforeach
                                            @foreach ($item['calc_ids'] ?? [] as $calcId)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-info-50 text-info-700">{{ $calcId }}</span>
                                            @endforeach
                                        </div>
                                        @foreach ($item['flags'] ?? [] as $flag)
                                            <p class="text-xs text-danger-600 mt-1">⚠ {{ $flag }}</p>
                                        @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        </x-filament::section>
                    @endforeach

                    @if (! empty($job->calculations))
                        <x-filament::section>
                            <x-slot name="heading">Perhitungan aplikasi</x-slot>
                            <table class="w-full text-sm">
                                <thead><tr class="text-left text-gray-500"><th>ID</th><th>Angka</th><th class="text-right">Realisasi</th><th class="text-right">Pembanding</th><th class="text-right">Selisih</th><th class="text-right">%</th></tr></thead>
                                <tbody>
                                @foreach ($job->calculations as $calc)
                                    <tr>
                                        <td>{{ $calc['id'] }}</td>
                                        <td>{{ $calc['label'] }} <span class="text-gray-500">({{ $calc['unit'] }}; {{ implode(', ', $calc['source_ids']) }})</span></td>
                                        <td class="text-right">{{ number_format($calc['actual'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($calc['baseline'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($calc['difference'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ $calc['percent'] === null ? '—' : number_format($calc['percent'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </x-filament::section>
                    @endif

                    @if (! empty($output['conflicts']))
                        <x-filament::section>
                            <x-slot name="heading">Sumber yang bertentangan</x-slot>
                            <ul class="list-disc ps-5 space-y-1">
                                @foreach ($output['conflicts'] as $conflict)
                                    <li><strong>{{ $conflict['topic'] }}</strong> ({{ implode(', ', $conflict['source_ids']) }}): {{ $conflict['note'] }}</li>
                                @endforeach
                            </ul>
                        </x-filament::section>
                    @endif

                    @if (! empty($output['missing_information']) || ! empty($output['limitations']) || ! empty($job->excluded_sources))
                        <x-filament::section>
                            <x-slot name="heading">Keterbatasan</x-slot>
                            <ul class="list-disc ps-5 space-y-1 text-sm">
                                @foreach ($output['missing_information'] ?? [] as $line)<li>Belum tersedia: {{ $line }}</li>@endforeach
                                @foreach ($output['limitations'] ?? [] as $line)<li>{{ $line }}</li>@endforeach
                                @foreach ($job->excluded_sources ?? [] as $excluded)<li>Tidak dikirim ke AI: {{ $excluded['title'] }} — {{ $excluded['reason'] }}</li>@endforeach
                            </ul>
                        </x-filament::section>
                    @endif
                </div>

                <div class="lg:col-span-2">
                    <div class="lg:sticky lg:top-20 space-y-3">
                        <x-filament::section>
                            <x-slot name="heading">Sumber ({{ count($sources) }})</x-slot>
                            <div class="flex flex-wrap gap-1 mb-3">
                                @foreach ($sources as $id => $source)
                                    <button type="button" x-on:click="src = @js($id)" class="text-xs px-2 py-0.5 rounded-full border"
                                        x-bind:class="src === @js($id) ? 'bg-primary-600 text-white' : ''">{{ $id }}</button>
                                @endforeach
                            </div>
                            <template x-if="! src"><p class="text-sm text-gray-500">Pilih rujukan untuk melihat teks sumber.</p></template>
                            @foreach ($sources as $id => $source)
                                <div x-show="src === @js($id)" x-cloak>
                                    <p class="font-semibold">{{ $id }} · {{ $source['title'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $source['location'] ?? '' }}
                                        @isset($source['version_no']) · versi {{ $source['version_no'] }} @endisset
                                        @isset($source['period']) · {{ $source['period'] }} @endisset
                                        @isset($source['status']) · {{ $source['status'] }} @endisset
                                    </p>
                                    @isset($source['version_id'])
                                        <a class="text-sm text-primary-600 underline" target="_blank"
                                           href="{{ route('berkas.lihat', $source['version_id']) }}{{ ! empty($source['page_no']) ? '#page='.$source['page_no'] : '' }}">Buka dokumen di lokasi ini</a>
                                    @endisset
                                    <pre class="mt-2 text-xs whitespace-pre-wrap max-h-[60vh] overflow-y-auto bg-gray-50 dark:bg-white/5 p-2 rounded">{{ $source['text'] }}</pre>
                                </div>
                            @endforeach
                        </x-filament::section>

                        <x-filament::section collapsible collapsed>
                            <x-slot name="heading">Jejak pemrosesan</x-slot>
                            <dl class="text-xs grid grid-cols-2 gap-1">
                                <dt>Model</dt><dd>{{ $job->model }}</dd>
                                <dt>Versi templat</dt><dd>{{ $job->template_version }}</dd>
                                <dt>Penyimpanan di OpenAI</dt><dd>store: false</dd>
                                <dt>Token masuk/keluar</dt><dd>{{ $job->input_tokens }} / {{ $job->output_tokens }}</dd>
                                <dt>Durasi</dt><dd>{{ number_format(($job->latency_ms ?? 0) / 1000, 1) }} detik</dd>
                                <dt>Rujukan palsu dibuang</dt><dd>{{ $job->validation['fabricated_citations'] ?? 0 }}</dd>
                                <dt>Fungsi dipanggil</dt><dd>{{ collect($job->tool_calls)->pluck('name')->implode(', ') ?: '—' }}</dd>
                            </dl>
                        </x-filament::section>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
