<x-filament-panels::page>
    <p class="aksi-muted">Ringkasan hitungan per perusahaan. Isi dokumen dan catatan tetap terpisah per perusahaan.</p>
    <x-filament::section>
        <table class="aksi-table">
            <thead>
                <tr><th>Perusahaan</th><th>Peran</th><th>Isu material terbuka</th><th>Tindak lanjut lewat tenggat</th><th>Menunggu jawaban</th><th>Rapat berikutnya</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><a class="aksi-link" href="{{ \App\Filament\App\Pages\BerandaSaya::getUrl(tenant: $row['company']) }}">{{ $row['company']->name }}</a></td>
                        <td>{{ $row['role'] }}</td>
                        <td class="{{ $row['high_issues'] ? 'aksi-danger' : '' }}">{{ $row['high_issues'] }}</td>
                        <td class="{{ $row['overdue'] ? 'aksi-danger' : '' }}">{{ $row['overdue'] ?? '—' }}</td>
                        <td>{{ $row['waiting'] }}</td>
                        <td>{{ $row['next_meeting']?->scheduled_at->translatedFormat('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
